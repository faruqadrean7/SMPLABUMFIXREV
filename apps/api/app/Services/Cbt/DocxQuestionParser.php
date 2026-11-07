<?php

namespace App\Services\Cbt;

use DOMDocument;
use DOMXPath;
use PhpOffice\PhpWord\IOFactory;
use Throwable;
use ZipArchive;

/**
 * BE-CBT-13: Parser berkas DOCX template soal CBT menjadi draf payload import.
 *
 * Mendukung format:
 * 1. Format Berbasis Tabel (Kolom: No, Tipe, Pertanyaan, Opsi A-D/E, Kunci, Bobot, Level, Pembahasan)
 * 2. Format Berbasis Blok Teks (Penanda [SOAL] atau penomoran 1., 2., dst dengan Opsi A., B., C., D. dan KUNCI:)
 */
class DocxQuestionParser
{
    /**
     * Parse file DOCX dan kembalikan array draf soal serta daftar kesalahan per butir.
     *
     * @return array{questions: array, errors: array}
     */
    public function parse(string $filePath): array
    {
        if (! file_exists($filePath)) {
            throw new \InvalidArgumentException("Berkas tidak ditemukan: {$filePath}");
        }

        // 1. Ekstrak teks dan tabel langsung dari OpenXML document.xml untuk akurasi maksimal
        $xmlContent = $this->extractDocumentXml($filePath);
        if (! $xmlContent) {
            // Fallback via PhpWord jika XML langsung tidak dapat dibaca
            return $this->parseViaPhpWord($filePath);
        }

        return $this->parseXmlContent($xmlContent);
    }

    /**
     * Ekstrak isi word/document.xml dari berkas .docx (ZIP archive).
     */
    protected function extractDocumentXml(string $filePath): ?string
    {
        $zip = new ZipArchive;
        if ($zip->open($filePath) !== true) {
            return null;
        }

        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        return $xml ?: null;
    }

    /**
     * Parse XML OpenXML Word document.
     */
    protected function parseXmlContent(string $xmlContent): array
    {
        $dom = new DOMDocument;
        // Supress warnings for OpenXML namespaces
        @$dom->loadXML($xmlContent, LIBXML_NOENT | LIBXML_XINCLUDE | LIBXML_NOERROR | LIBXML_NOWARNING);

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
        $xpath->registerNamespace('m', 'http://schemas.openxmlformats.org/officeDocument/2006/math');

        // Cek apakah ada tabel di dalam dokumen
        $tables = $xpath->query('//w:tbl');
        if ($tables && $tables->length > 0) {
            $tableResult = $this->parseTableDocument($xpath, $tables);
            if (! empty($tableResult['questions'])) {
                return $tableResult;
            }
        }

        // Jika bukan format tabel, parse sebagai teks berparagraf/blok
        return $this->parseParagraphDocument($xpath);
    }

    /**
     * Parse dokumen berbasis tabel template.
     */
    protected function parseTableDocument(DOMXPath $xpath, \DOMNodeList $tables): array
    {
        $questions = [];
        $errors = [];

        foreach ($tables as $table) {
            $rows = $xpath->query('.//w:tr', $table);
            if (! $rows || $rows->length < 2) {
                continue;
            }

            // Ambil header baris pertama
            $headerRow = $rows->item(0);
            $headerCells = $xpath->query('.//w:tc', $headerRow);
            $headers = [];
            foreach ($headerCells as $idx => $cell) {
                $headers[$idx] = strtolower(trim($this->extractTextFromNode($xpath, $cell)));
            }

            // Cari index kolom
            $colMap = $this->resolveTableColumns($headers);
            if (! isset($colMap['question'])) {
                continue; // Bukan tabel soal
            }

            // Parse tiap baris data
            for ($i = 1; $i < $rows->length; $i++) {
                $row = $rows->item($i);
                $cells = $xpath->query('.//w:tc', $row);
                if ($cells->length < 2) {
                    continue;
                }

                $rowData = [];
                foreach ($cells as $idx => $cell) {
                    $rowData[$idx] = trim($this->extractTextFromNode($xpath, $cell));
                }

                $qNum = isset($colMap['number'], $rowData[$colMap['number']]) && $rowData[$colMap['number']] !== ''
                    ? (int) preg_replace('/[^0-9]/', '', $rowData[$colMap['number']])
                    : count($questions) + 1;

                $qText = $rowData[$colMap['question']] ?? '';
                if ($qText === '') {
                    continue; // Lewati baris kosong
                }

                $qType = isset($colMap['type'], $rowData[$colMap['type']])
                    ? $this->normalizeType($rowData[$colMap['type']])
                    : 'pg';

                $key = isset($colMap['key'], $rowData[$colMap['key']]) ? trim($rowData[$colMap['key']]) : '';
                $weight = isset($colMap['weight'], $rowData[$colMap['weight']]) ? (float) str_replace(',', '.', $rowData[$colMap['weight']]) : 1.0;
                $level = isset($colMap['level'], $rowData[$colMap['level']]) ? $this->normalizeLevel($rowData[$colMap['level']]) : 'MOTS';
                $explanation = isset($colMap['explanation'], $rowData[$colMap['explanation']]) ? $rowData[$colMap['explanation']] : null;

                // Ekstrak opsi A, B, C, D, E
                $options = [];
                foreach (['a' => 'A', 'b' => 'B', 'c' => 'C', 'd' => 'D', 'e' => 'E'] as $keyName => $label) {
                    if (isset($colMap[$keyName], $rowData[$colMap[$keyName]]) && $rowData[$colMap[$keyName]] !== '') {
                        $isCorrect = (strtoupper($key) === $label);
                        $options[] = [
                            'label' => $label,
                            'body' => ['text' => $rowData[$colMap[$keyName]]],
                            'is_correct' => $isCorrect,
                            'match_key' => null,
                        ];
                    }
                }

                $parsedItem = $this->buildQuestionItem($qNum, $qType, $qText, $options, $key, $weight, $level, $explanation);

                if (! empty($parsedItem['error'])) {
                    $errors[] = ['item_number' => $qNum, 'error' => $parsedItem['error']];
                }

                $questions[] = $parsedItem['question'];
            }
        }

        return [
            'questions' => $questions,
            'errors' => $errors,
        ];
    }

    /**
     * Resolusi mapping index kolom tabel.
     */
    protected function resolveTableColumns(array $headers): array
    {
        $map = [];
        foreach ($headers as $idx => $h) {
            if (preg_match('/^(no|nomor|number)/i', $h)) {
                $map['number'] = $idx;
            } elseif (preg_match('/^(tipe|jenis|type)/i', $h)) {
                $map['type'] = $idx;
            } elseif (preg_match('/^(soal|pertanyaan|isi soal|question)/i', $h)) {
                $map['question'] = $idx;
            } elseif (preg_match('/^(opsi a|pilihan a|a)$/i', $h)) {
                $map['a'] = $idx;
            } elseif (preg_match('/^(opsi b|pilihan b|b)$/i', $h)) {
                $map['b'] = $idx;
            } elseif (preg_match('/^(opsi c|pilihan c|c)$/i', $h)) {
                $map['c'] = $idx;
            } elseif (preg_match('/^(opsi d|pilihan d|d)$/i', $h)) {
                $map['d'] = $idx;
            } elseif (preg_match('/^(opsi e|pilihan e|e)$/i', $h)) {
                $map['e'] = $idx;
            } elseif (preg_match('/^(kunci|jawaban|answer|key)/i', $h)) {
                $map['key'] = $idx;
            } elseif (preg_match('/^(bobot|weight|skor)/i', $h)) {
                $map['weight'] = $idx;
            } elseif (preg_match('/^(level|kognitif|cognitive|ranah)/i', $h)) {
                $map['level'] = $idx;
            } elseif (preg_match('/^(pembahasan|penjelasan|explanation)/i', $h)) {
                $map['explanation'] = $idx;
            }
        }

        return $map;
    }

    /**
     * Parse dokumen berbasis paragraf / teks bebas berstruktur.
     */
    protected function parseParagraphDocument(DOMXPath $xpath): array
    {
        $paragraphs = $xpath->query('//w:p');
        $lines = [];
        foreach ($paragraphs as $p) {
            $text = trim($this->extractTextFromNode($xpath, $p));
            if ($text !== '') {
                $lines[] = $text;
            }
        }

        return $this->parseLines($lines);
    }

    /**
     * Parse array baris teks menjadi butir soal.
     */
    public function parseLines(array $lines): array
    {
        $questions = [];
        $errors = [];
        $currentBlock = null;

        foreach ($lines as $line) {
            // Cek apakah ini awal soal baru (misal: "1.", "[SOAL]", "Soal 1", "1. [PG]")
            if (preg_match('/^(?:\[SOAL\]|(?:\d+[\.\)]\s*)|(?:SOAL\s+\d+[:\.]?\s*))/i', $line)) {
                if ($currentBlock) {
                    $res = $this->finalizeBlock($currentBlock);
                    if (! empty($res['error'])) {
                        $errors[] = ['item_number' => $res['question_number'], 'error' => $res['error']];
                    }
                    $questions[] = $res['question'];
                }

                // Mulai blok baru
                $qNum = count($questions) + 1;
                if (preg_match('/^(\d+)[\.\)]/i', $line, $m)) {
                    $qNum = (int) $m[1];
                } elseif (preg_match('/SOAL\s+(\d+)/i', $line, $m)) {
                    $qNum = (int) $m[1];
                }

                $currentBlock = [
                    'number' => $qNum,
                    'type' => 'pg',
                    'weight' => 1.0,
                    'level' => 'MOTS',
                    'question_lines' => [],
                    'options' => [],
                    'key' => null,
                    'explanation' => null,
                ];

                // Cek metadata pada baris pertama (misal: [PG] [BOBOT: 2] [LEVEL: HOTS])
                if (preg_match('/\[(PG|BS|ISIAN|URAIAN)\]/i', $line, $m)) {
                    $currentBlock['type'] = $this->normalizeType($m[1]);
                }
                if (preg_match('/\[BOBOT:\s*([0-9\.,]+)\]/i', $line, $m)) {
                    $currentBlock['weight'] = (float) str_replace(',', '.', $m[1]);
                }
                if (preg_match('/\[LEVEL:\s*(LOTS|MOTS|HOTS)\]/i', $line, $m)) {
                    $currentBlock['level'] = strtoupper($m[1]);
                }

                // Bersihkan penanda awal dari teks soal
                $cleanLine = preg_replace('/^(?:\[SOAL\]|(?:\d+[\.\)]\s*)|(?:SOAL\s+\d+[:\.]?\s*))/i', '', $line);
                $cleanLine = preg_replace('/\[(PG|BS|ISIAN|URAIAN)\]/i', '', $cleanLine);
                $cleanLine = preg_replace('/\[BOBOT:\s*([0-9\.,]+)\]/i', '', $cleanLine);
                $cleanLine = preg_replace('/\[LEVEL:\s*(LOTS|MOTS|HOTS)\]/i', '', $cleanLine);
                $cleanLine = trim($cleanLine);

                if ($cleanLine !== '') {
                    $currentBlock['question_lines'][] = $cleanLine;
                }

                continue;
            }

            if (! $currentBlock) {
                continue; // Belum masuk blok soal pertama
            }

            // Cek baris tipe eksplisit: "Tipe: PG"
            if (preg_match('/^(?:Tipe|Jenis|Type)\s*:\s*(PG|BS|ISIAN|URAIAN)/i', $line, $m)) {
                $currentBlock['type'] = $this->normalizeType($m[1]);

                continue;
            }

            // Cek baris bobot eksplisit: "Bobot: 2"
            if (preg_match('/^(?:Bobot|Skor|Weight)\s*:\s*([0-9\.,]+)/i', $line, $m)) {
                $currentBlock['weight'] = (float) str_replace(',', '.', $m[1]);

                continue;
            }

            // Cek baris level kognitif: "Level: HOTS"
            if (preg_match('/^(?:Level|Tingkat|Cognitive)\s*:\s*(LOTS|MOTS|HOTS)/i', $line, $m)) {
                $currentBlock['level'] = strtoupper($m[1]);

                continue;
            }

            // Cek baris kunci: "Kunci: A" atau "Kunci Jawaban: B"
            if (preg_match('/^(?:Kunci(?:\s+Jawaban)?|Key|Answer)\s*:\s*(.+)$/i', $line, $m)) {
                $currentBlock['key'] = trim($m[1]);

                continue;
            }

            // Cek baris pembahasan: "Pembahasan: ..."
            if (preg_match('/^(?:Pembahasan|Explanation|Penjelasan)\s*:\s*(.+)$/i', $line, $m)) {
                $currentBlock['explanation'] = trim($m[1]);

                continue;
            }

            // Cek opsi pilihan ganda: "A. ...", "*B. ...", "(A) ...", "A) ..."
            if (preg_match('/^(\*?)\s*(?:[\(\[]?([A-Ea-e])[\)\]\.]|\b([A-Ea-e])\.)\s*(.+)$/', $line, $m)) {
                $isStarred = ($m[1] === '*');
                $label = strtoupper($m[2] ?: $m[3]);
                $optText = trim($m[4]);
                $currentBlock['options'][$label] = [
                    'text' => $optText,
                    'is_starred' => $isStarred,
                ];

                continue;
            }

            // Sisanya dimasukkan ke teks soal
            $currentBlock['question_lines'][] = $line;
        }

        if ($currentBlock) {
            $res = $this->finalizeBlock($currentBlock);
            if (! empty($res['error'])) {
                $errors[] = ['item_number' => $res['question_number'], 'error' => $res['error']];
            }
            $questions[] = $res['question'];
        }

        return [
            'questions' => $questions,
            'errors' => $errors,
        ];
    }

    /**
     * Selesaikan blok pertanyaan teks menjadi Question payload terstruktur.
     */
    protected function finalizeBlock(array $block): array
    {
        $qNum = $block['number'];
        $qType = $block['type'];
        $qText = implode("\n", $block['question_lines']);
        $key = $block['key'];

        // Jika ada opsi bertanda bintang (*A atau *B), gunakan sebagai kunci jika belum ada kunci eksplisit
        if (! $key && ! empty($block['options'])) {
            foreach ($block['options'] as $label => $opt) {
                if ($opt['is_starred']) {
                    $key = $label;
                    break;
                }
            }
        }

        // Format opsi
        $options = [];
        if (! empty($block['options'])) {
            foreach ($block['options'] as $label => $opt) {
                $isCorrect = false;
                if ($key && strtoupper($key) === $label) {
                    $isCorrect = true;
                } elseif ($opt['is_starred']) {
                    $isCorrect = true;
                }

                $options[] = [
                    'label' => $label,
                    'body' => ['text' => $opt['text']],
                    'is_correct' => $isCorrect,
                    'match_key' => null,
                ];
            }
        } elseif ($qType === 'bs') {
            // Auto build options Benar / Salah jika belum ada opsi eksplisit
            $options = [
                ['label' => 'A', 'body' => ['text' => 'Benar'], 'is_correct' => (strtoupper($key ?? '') === 'A' || strtolower($key ?? '') === 'benar' || strtoupper($key ?? '') === 'B'), 'match_key' => null],
                ['label' => 'B', 'body' => ['text' => 'Salah'], 'is_correct' => (strtoupper($key ?? '') === 'B' || strtolower($key ?? '') === 'salah' || strtoupper($key ?? '') === 'S'), 'match_key' => null],
            ];
        }

        $res = $this->buildQuestionItem($qNum, $qType, $qText, $options, $key, $block['weight'], $block['level'], $block['explanation']);

        return [
            'question_number' => $qNum,
            'question' => $res['question'],
            'error' => $res['error'],
        ];
    }

    /**
     * Membangun entri soal terstruktur dan memvalidasi aturan bisnis (AC7).
     */
    protected function buildQuestionItem(
        int $number,
        string $type,
        string $text,
        array $options,
        ?string $key,
        float $weight,
        string $level,
        ?string $explanation
    ): array {
        $error = null;

        // Validasi AC7: PG dan BS wajib memiliki tepat satu is_correct = true
        if ($type === 'pg') {
            $correctCount = count(array_filter($options, fn ($o) => ! empty($o['is_correct'])));
            if ($correctCount !== 1) {
                $error = "Butir soal #{$number} bertipe Pilihan Ganda (PG) wajib memiliki tepat 1 kunci jawaban yang benar (ditemukan: {$correctCount}).";
            }
        } elseif ($type === 'bs') {
            $correctCount = count(array_filter($options, fn ($o) => ! empty($o['is_correct'])));
            if ($correctCount !== 1) {
                $error = "Butir soal #{$number} bertipe Benar/Salah (B/S) wajib memiliki tepat 1 kunci jawaban yang benar (ditemukan: {$correctCount}).";
            }
        } elseif ($type === 'isian') {
            if (empty($key) && empty($options)) {
                $error = "Butir soal #{$number} bertipe Isian Singkat wajib menyertakan kunci jawaban.";
            } elseif ($key && empty($options)) {
                // Buat opsi kunci untuk isian jika belum ada
                $options = [
                    ['label' => 'KUNCI', 'body' => ['text' => $key], 'is_correct' => true, 'match_key' => null],
                ];
            }
        }

        $question = [
            'question_type' => $type,
            'body' => ['content' => $text ?: "Butir soal nomor {$number}"],
            'cognitive_level' => $level,
            'difficulty' => 3,
            'weight' => max(0.1, min(100.0, $weight)),
            'explanation' => $explanation,
            'options' => $options,
        ];

        return [
            'question' => $question,
            'error' => $error,
        ];
    }

    /**
     * Ekstrak teks utuh dari DOMNode termasuk teks biasa dan teks formula/math.
     */
    protected function extractTextFromNode(DOMXPath $xpath, \DOMNode $node): string
    {
        $textParts = [];
        $textNodes = $xpath->query('.//w:t | .//m:t', $node);
        if ($textNodes) {
            foreach ($textNodes as $t) {
                $textParts[] = $t->nodeValue;
            }
        }

        return implode('', $textParts);
    }

    /**
     * Fallback parser menggunakan PhpOffice\PhpWord jika XML mentah bermasalah.
     */
    protected function parseViaPhpWord(string $filePath): array
    {
        try {
            $phpWord = IOFactory::load($filePath);
            $lines = [];

            foreach ($phpWord->getSections() as $section) {
                foreach ($section->getElements() as $element) {
                    if (method_exists($element, 'getText')) {
                        $txt = $element->getText();
                        if (is_string($txt) && trim($txt) !== '') {
                            $lines[] = trim($txt);
                        }
                    } elseif (method_exists($element, 'getElements')) {
                        $innerTexts = [];
                        foreach ($element->getElements() as $subElement) {
                            if (method_exists($subElement, 'getText')) {
                                $subTxt = $subElement->getText();
                                if (is_string($subTxt)) {
                                    $innerTexts[] = $subTxt;
                                }
                            }
                        }
                        if (! empty($innerTexts)) {
                            $lines[] = trim(implode('', $innerTexts));
                        }
                    }
                }
            }

            return $this->parseLines($lines);
        } catch (Throwable $e) {
            return [
                'questions' => [],
                'errors' => [['item_number' => 0, 'error' => 'Gagal membaca berkas DOCX: '.$e->getMessage()]],
            ];
        }
    }

    /**
     * Normalisasi tipe soal ke enum CBT API.
     */
    protected function normalizeType(string $raw): string
    {
        $clean = strtolower(trim($raw));

        return match ($clean) {
            'pg', 'pilihan ganda', 'pilihan_ganda', 'multiple choice' => 'pg',
            'bs', 'benar salah', 'benar_salah', 'b/s', 'true false' => 'bs',
            'isian', 'isian singkat', 'isian_singkat', 'short answer' => 'isian',
            'uraian', 'essay', 'esai' => 'uraian',
            'pg_kompleks', 'pilihan ganda kompleks' => 'pg_kompleks',
            'menjodohkan', 'matching' => 'menjodohkan',
            default => 'pg',
        };
    }

    /**
     * Normalisasi level kognitif ke LOTS | MOTS | HOTS.
     */
    protected function normalizeLevel(string $raw): string
    {
        $clean = strtoupper(trim($raw));

        return match ($clean) {
            'LOTS', 'C1', 'C2', 'MUDAH' => 'LOTS',
            'HOTS', 'C5', 'C6', 'SULIT' => 'HOTS',
            default => 'MOTS',
        };
    }
}
