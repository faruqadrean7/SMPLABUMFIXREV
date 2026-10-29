import { redirect } from 'next/navigation';

// Dedup: guru/cbt (prototipe dgn ID hardcoded + monitor mock) digantikan oleh
// guru/ujian yang sudah terhubung backend nyata (useTeacherExams + bank soal).
export default function Page() {
  redirect('/portal/guru/ujian');
}
