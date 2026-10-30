<?php

namespace Tests\Unit\Ppdb;

use App\Models\Applicant;
use App\Models\ApplicantDocument;
use App\Models\ApplicantParent;
use App\Models\File;
use App\Services\PPDBApplicationService;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * Memastikan presentDetail() memetakan data ke kontrak portal admin:
 * - field biodata diambil dari step_data (nisn, nik, tempat lahir, gender, alamat, asal sekolah)
 * - nama ortu dari kolom `name`/`parent_type` -> full_name/relationship
 * - dokumen menyertakan objek file (agar tombol "Buka File" tampil)
 */
class ApplicantDetailMappingTest extends TestCase
{
    private function makeApplicant(): Applicant
    {
        $a = new Applicant();
        $a->forceFill([
            'id' => 'app-1',
            'user_id' => 'user-1',
            'wave_id' => 'wave-1',
            'track_id' => 'track-1',
            'registration_no' => 'PPDB-2026-00002',
            'full_name' => 'Faruq Adrean',
            'birth_date' => '2008-02-07',
            'status' => 'submitted',
            'step_data' => [
                'personal' => [
                    'nisn' => '0081234567',
                    'nik' => '3573010101080001',
                    'birth_place' => 'Malang',
                    'gender' => 'P',
                    'address' => 'Jl. Semarang 5',
                    'religion' => 'Islam',
                ],
                'school' => [
                    'origin_school' => 'SDN Percobaan 1',
                ],
            ],
        ]);
        $a->setRelation('wave', null);
        $a->setRelation('track', null);

        $parent = new ApplicantParent();
        $parent->forceFill([
            'id' => 'par-1',
            'applicant_id' => 'app-1',
            'parent_type' => 'ayah',
            'name' => 'Budi Santoso',
            'occupation' => 'Swasta',
            'phone' => '08923213213',
            'income_range' => '< 3 Juta',
        ]);
        $a->setRelation('parents', new Collection([$parent]));

        $file = new File();
        $file->forceFill([
            'id' => 'file-1',
            'object_key' => 'ppdb/app-1/rapor.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 20480,
        ]);
        $doc = new ApplicantDocument();
        $doc->forceFill([
            'id' => 'doc-1',
            'applicant_id' => 'app-1',
            'document_type' => 'RAPOR',
            'file_id' => 'file-1',
            'status' => 'valid',
        ]);
        $doc->setRelation('file', $file);
        $doc->setRelation('verifier', null);
        $a->setRelation('documents', new Collection([$doc]));

        $a->setRelation('statusHistory', new Collection([]));

        return $a;
    }

    public function test_biodata_fields_are_pulled_from_step_data(): void
    {
        $out = (new PPDBApplicationService())->presentDetail($this->makeApplicant());

        $this->assertSame('0081234567', $out['nisn']);
        $this->assertSame('3573010101080001', $out['nik']);
        $this->assertSame('Malang', $out['birth_place']);
        $this->assertSame('P', $out['gender']);
        $this->assertSame('Jl. Semarang 5', $out['address']);
        $this->assertSame('SDN Percobaan 1', $out['school_origin']);
        $this->assertSame('Faruq Adrean', $out['full_name']);
    }

    public function test_parent_name_and_relationship_are_mapped(): void
    {
        $out = (new PPDBApplicationService())->presentDetail($this->makeApplicant());

        $this->assertCount(1, $out['parents']);
        $parent = $out['parents']->first();
        $this->assertSame('Budi Santoso', $parent['full_name']);
        $this->assertSame('ayah', $parent['relationship']);
        $this->assertSame('Swasta', $parent['occupation']);
        $this->assertSame('08923213213', $parent['phone']);
    }

    public function test_document_includes_file_object_for_view_button(): void
    {
        $out = (new PPDBApplicationService())->presentDetail($this->makeApplicant());

        $this->assertCount(1, $out['documents']);
        $doc = $out['documents']->first();
        $this->assertSame('valid', $doc['status']);
        $this->assertIsArray($doc['file']);
        $this->assertSame('file-1', $doc['file']['id']);
        $this->assertSame('rapor.pdf', $doc['file']['filename']);
    }
}
