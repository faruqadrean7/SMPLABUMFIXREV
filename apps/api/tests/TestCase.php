<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Carbon;

abstract class TestCase extends BaseTestCase
{
    /**
     * Bersihkan state global antar test.
     *
     * Banyak test memakai Carbon::setTestNow() untuk memalsukan waktu. Tanpa
     * reset, waktu palsu itu terbawa ke test berikutnya dan membuat hasil
     * bergantung urutan (mis. rumus skor CBT gagal hanya saat dijalankan
     * setelah suite lain). Reset di sini membuat tiap test mulai dari waktu asli.
     */
    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }
}
