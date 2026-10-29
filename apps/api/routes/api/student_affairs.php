<?php

use App\Http\Controllers\Api\V1\Std\AchievementController;
use App\Http\Controllers\Api\V1\Std\CompetitionController;
use App\Http\Controllers\Api\V1\Std\ExtracurricularController;
use App\Http\Controllers\Api\V1\Std\PointTypeController;
use App\Http\Controllers\Api\V1\Std\StudentPointController;
use App\Http\Controllers\Api\V1\Std\TripController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    // =====================================================================
    // BE-STD-01: Kesiswaan - Poin Pelanggaran & Prestasi
    // =====================================================================
    // Master Jenis Poin
    Route::get('/point-types', [PointTypeController::class, 'indexTypes'])
        ->middleware('permission:student.point.read');
    Route::post('/point-types', [PointTypeController::class, 'storeType'])
        ->middleware('permission:student.point.write');
    Route::get('/point-types/{id}', [PointTypeController::class, 'showType'])
        ->middleware('permission:student.point.read');
    Route::put('/point-types/{id}', [PointTypeController::class, 'updateType'])
        ->middleware('permission:student.point.write');
    Route::delete('/point-types/{id}', [PointTypeController::class, 'destroyType'])
        ->middleware('permission:student.point.write');

    // Master Ambang Batas Poin / Sanksi
    Route::get('/point-thresholds', [PointTypeController::class, 'indexThresholds'])
        ->middleware('permission:student.point.read');
    Route::post('/point-thresholds', [PointTypeController::class, 'storeThreshold'])
        ->middleware('permission:student.point.write');
    Route::get('/point-thresholds/{id}', [PointTypeController::class, 'showThreshold'])
        ->middleware('permission:student.point.read');
    Route::put('/point-thresholds/{id}', [PointTypeController::class, 'updateThreshold'])
        ->middleware('permission:student.point.write');
    Route::delete('/point-thresholds/{id}', [PointTypeController::class, 'destroyThreshold'])
        ->middleware('permission:student.point.write');

    // Pencatatan Poin Siswa
    Route::get('/student-points', [StudentPointController::class, 'index'])
        ->middleware('permission:student.point.read');
    Route::post('/student-points', [StudentPointController::class, 'store'])
        ->middleware('permission:student.point.write');
    Route::get('/student-points/{id}', [StudentPointController::class, 'show'])
        ->middleware('permission:student.point.read');
    Route::delete('/student-points/{id}', [StudentPointController::class, 'destroy'])
        ->middleware('permission:student.point.write');

    // Ringkasan Poin Siswa
    Route::get('/students/{id}/points/summary', [StudentPointController::class, 'summary'])
        ->middleware('permission:student.point.read,student,siswa,parent,guardian');

    // =====================================================================
    // BE-STD-02: Kesiswaan - Ekstrakurikuler & OSIS
    // =====================================================================
    // Ekstrakurikuler
    Route::get('/extracurriculars', [ExtracurricularController::class, 'index'])
        ->middleware('permission:super_admin,admin,teacher,kesiswaan,student_affairs,student,siswa,guardian,parent');
    Route::post('/extracurriculars', [ExtracurricularController::class, 'store'])
        ->middleware('permission:super_admin,admin,kesiswaan,student_affairs');
    Route::get('/extracurriculars/{id}', [ExtracurricularController::class, 'show'])
        ->middleware('permission:super_admin,admin,teacher,kesiswaan,student_affairs,student,siswa,guardian,parent');
    Route::put('/extracurriculars/{id}', [ExtracurricularController::class, 'update'])
        ->middleware('permission:super_admin,admin,kesiswaan,student_affairs,teacher');
    Route::delete('/extracurriculars/{id}', [ExtracurricularController::class, 'destroy'])
        ->middleware('permission:super_admin,admin,kesiswaan,student_affairs');

    // Anggota Ekstrakurikuler (Pendaftaran & Penilaian)
    Route::get('/extracurricular-members', [ExtracurricularController::class, 'indexMembers'])
        ->middleware('permission:super_admin,admin,teacher,kesiswaan,student_affairs,student,siswa');
    Route::post('/extracurricular-members', [ExtracurricularController::class, 'registerMember'])
        ->middleware('permission:super_admin,admin,teacher,kesiswaan,student_affairs,student,siswa');
    Route::put('/extracurricular-members/{id}', [ExtracurricularController::class, 'updateMember'])
        ->middleware('permission:super_admin,admin,teacher,kesiswaan,student_affairs');
    Route::delete('/extracurricular-members/{id}', [ExtracurricularController::class, 'removeMember'])
        ->middleware('permission:super_admin,admin,teacher,kesiswaan,student_affairs,student,siswa');

    // Jurnal Kegiatan & Presensi
    Route::get('/extracurricular-journals', [ExtracurricularController::class, 'indexJournals'])
        ->middleware('permission:super_admin,admin,teacher,kesiswaan,student_affairs,student,siswa');
    Route::post('/extracurricular-journals', [ExtracurricularController::class, 'storeJournal'])
        ->middleware('permission:super_admin,admin,teacher,kesiswaan,student_affairs');
    Route::get('/extracurricular-journals/{id}', [ExtracurricularController::class, 'showJournal'])
        ->middleware('permission:super_admin,admin,teacher,kesiswaan,student_affairs,student,siswa');
    Route::delete('/extracurricular-journals/{id}', [ExtracurricularController::class, 'destroyJournal'])
        ->middleware('permission:super_admin,admin,teacher,kesiswaan,student_affairs');

    // Dokumen Kegiatan / Proposal / Laporan (OSIS & Ekskul)
    Route::get('/activity-documents', [ExtracurricularController::class, 'indexDocuments'])
        ->middleware('permission:super_admin,admin,teacher,kesiswaan,student_affairs,student,siswa');
    Route::post('/activity-documents', [ExtracurricularController::class, 'storeDocument'])
        ->middleware('permission:super_admin,admin,teacher,kesiswaan,student_affairs');
    Route::get('/activity-documents/{id}', [ExtracurricularController::class, 'showDocument'])
        ->middleware('permission:super_admin,admin,teacher,kesiswaan,student_affairs,student,siswa');
    Route::delete('/activity-documents/{id}', [ExtracurricularController::class, 'destroyDocument'])
        ->middleware('permission:super_admin,admin,teacher,kesiswaan,student_affairs');

    // =====================================================================
    // BE-STD-03: Kesiswaan - Lomba & Prestasi
    // =====================================================================
    // Kompetisi & Delegasi Lomba
    Route::get('/competitions', [CompetitionController::class, 'index'])
        ->middleware('permission:super_admin,admin,teacher,kesiswaan,student_affairs,student,siswa,guardian,parent');
    Route::get('/competitions/summary', [CompetitionController::class, 'summary'])
        ->middleware('permission:super_admin,admin,teacher,kesiswaan,student_affairs');
    Route::post('/competitions', [CompetitionController::class, 'store'])
        ->middleware('permission:super_admin,admin,teacher,kesiswaan,student_affairs');
    Route::get('/competitions/{id}', [CompetitionController::class, 'show'])
        ->middleware('permission:super_admin,admin,teacher,kesiswaan,student_affairs,student,siswa,guardian,parent');
    Route::put('/competitions/{id}', [CompetitionController::class, 'update'])
        ->middleware('permission:super_admin,admin,teacher,kesiswaan,student_affairs');
    Route::delete('/competitions/{id}', [CompetitionController::class, 'destroy'])
        ->middleware('permission:super_admin,admin,kesiswaan,student_affairs');

    Route::get('/competitions/{id}/entries', [CompetitionController::class, 'indexEntries'])
        ->middleware('permission:super_admin,admin,teacher,kesiswaan,student_affairs,student,siswa');
    Route::post('/competitions/{id}/entries', [CompetitionController::class, 'storeEntry'])
        ->middleware('permission:super_admin,admin,teacher,kesiswaan,student_affairs');
    Route::put('/competition-entries/{id}', [CompetitionController::class, 'updateEntry'])
        ->middleware('permission:super_admin,admin,teacher,kesiswaan,student_affairs');
    Route::delete('/competition-entries/{id}', [CompetitionController::class, 'destroyEntry'])
        ->middleware('permission:super_admin,admin,teacher,kesiswaan,student_affairs');

    // Prestasi & Galeri Piala
    Route::get('/achievements', [AchievementController::class, 'index'])
        ->middleware('permission:super_admin,admin,teacher,kesiswaan,student_affairs,student,siswa,guardian,parent');
    Route::post('/achievements', [AchievementController::class, 'store'])
        ->middleware('permission:super_admin,admin,teacher,kesiswaan,student_affairs,student,siswa,guardian,parent');
    Route::get('/achievements/{id}', [AchievementController::class, 'show'])
        ->middleware('permission:super_admin,admin,teacher,kesiswaan,student_affairs,student,siswa,guardian,parent');
    Route::put('/achievements/{id}', [AchievementController::class, 'update'])
        ->middleware('permission:super_admin,admin,teacher,kesiswaan,student_affairs,student,siswa,guardian,parent');
    Route::patch('/achievements/{id}/verify', [AchievementController::class, 'verify'])
        ->middleware('permission:super_admin,admin,kesiswaan,student_affairs');
    Route::delete('/achievements/{id}', [AchievementController::class, 'destroy'])
        ->middleware('permission:super_admin,admin,teacher,kesiswaan,student_affairs,student,siswa');

    // =====================================================================
    // BE-STD-04: Kesiswaan - Study Trip & Outing Class
    // =====================================================================
    // Master Perjalanan (Trips)
    Route::get('/trips', [TripController::class, 'index'])
        ->middleware('permission:super_admin,admin,teacher,kesiswaan,student_affairs,student,siswa,guardian,parent');
    Route::post('/trips', [TripController::class, 'store'])
        ->middleware('permission:super_admin,admin,teacher,kesiswaan,student_affairs');
    Route::get('/trips/{id}', [TripController::class, 'show'])
        ->middleware('permission:super_admin,admin,teacher,kesiswaan,student_affairs,student,siswa,guardian,parent');
    Route::put('/trips/{id}', [TripController::class, 'update'])
        ->middleware('permission:super_admin,admin,teacher,kesiswaan,student_affairs');
    Route::delete('/trips/{id}', [TripController::class, 'destroy'])
        ->middleware('permission:super_admin,admin,kesiswaan,student_affairs');

    // Peserta Perjalanan (Participants)
    Route::get('/trips/{id}/participants', [TripController::class, 'indexParticipants'])
        ->middleware('permission:super_admin,admin,teacher,kesiswaan,student_affairs,student,siswa,guardian,parent');
    Route::post('/trips/{id}/participants', [TripController::class, 'storeParticipant'])
        ->middleware('permission:super_admin,admin,teacher,kesiswaan,student_affairs');
    Route::delete('/trip-participants/{id}', [TripController::class, 'destroyParticipant'])
        ->middleware('permission:super_admin,admin,teacher,kesiswaan,student_affairs');

    // Izin Digital Orang Tua (Parental Consents)
    Route::get('/trips/{id}/consents', [TripController::class, 'indexConsents'])
        ->middleware('permission:super_admin,admin,teacher,kesiswaan,student_affairs,student,siswa,guardian,parent');
    Route::post('/trips/{id}/consents', [TripController::class, 'storeConsent'])
        ->middleware('permission:guardian,parent,student,siswa,super_admin,admin,teacher,kesiswaan,student_affairs');
});
