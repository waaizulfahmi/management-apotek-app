<?php

namespace App\Console\Commands;

use App\Models\Obat;
use App\Models\User;
use App\Services\ImageService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ConvertImagesToWebp extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'images:convert-to-webp {--dry-run : Run without modifying database or deleting original files}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Convert all existing medicine images, cashier profiles, and logos to lightweight WebP format and update database';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $dryRun = $this->option('dry-run');

        $this->info("=== Memulai Konversi Gambar ke Format WebP ===");
        if ($dryRun) {
            $this->warn("[DRY RUN MODE] Tidak ada file atau database yang diubah.");
        }

        $totalOriginalBytes = 0;
        $totalWebpBytes = 0;
        $convertedCount = 0;

        // 1. Convert Obat Images
        $this->newLine();
        $this->info("1. Mengonversi Gambar Obat (public/Assets/Obat)...");
        $obatDir = public_path('Assets/Obat');
        if (file_exists($obatDir)) {
            $files = glob($obatDir . '/*.*');
            foreach ($files as $file) {
                $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                $filename = basename($file);

                if (in_array($ext, ['png', 'jpg', 'jpeg', 'bmp', 'gif'])) {
                    $originalSize = filesize($file);
                    $newFilename = ImageService::uploadAsWebp($file, $obatDir, 82);
                    $newPath = $obatDir . DIRECTORY_SEPARATOR . $newFilename;

                    if (file_exists($newPath) && filesize($newPath) > 0) {
                        $webpSize = filesize($newPath);
                        $totalOriginalBytes += $originalSize;
                        $totalWebpBytes += $webpSize;
                        $convertedCount++;

                        $this->line("  ✓ {$filename} (" . round($originalSize / 1024, 1) . " KB) -> {$newFilename} (" . round($webpSize / 1024, 1) . " KB)");

                        if (!$dryRun) {
                            // Update database record in obats
                            Obat::where('gambar', $filename)->update(['gambar' => $newFilename]);

                            // Also unlink old image if different name
                            if ($newFilename !== $filename && file_exists($file)) {
                                @unlink($file);
                            }
                        }
                    }
                }
            }
        }

        // 2. Convert Kasir Profiles
        $this->newLine();
        $this->info("2. Mengonversi Foto Profil Kasir (public/Assets/Kasir)...");
        $kasirDir = public_path('Assets/Kasir');
        if (file_exists($kasirDir)) {
            $files = glob($kasirDir . '/*.*');
            foreach ($files as $file) {
                $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                $filename = basename($file);

                if (in_array($ext, ['png', 'jpg', 'jpeg', 'bmp', 'gif'])) {
                    $originalSize = filesize($file);
                    $newFilename = ImageService::uploadAsWebp($file, $kasirDir, 82);
                    $newPath = $kasirDir . DIRECTORY_SEPARATOR . $newFilename;

                    if (file_exists($newPath) && filesize($newPath) > 0) {
                        $webpSize = filesize($newPath);
                        $totalOriginalBytes += $originalSize;
                        $totalWebpBytes += $webpSize;
                        $convertedCount++;

                        $this->line("  ✓ {$filename} (" . round($originalSize / 1024, 1) . " KB) -> {$newFilename} (" . round($webpSize / 1024, 1) . " KB)");

                        if (!$dryRun) {
                            User::where('profil', $filename)->update(['profil' => $newFilename]);

                            if ($newFilename !== $filename && file_exists($file)) {
                                @unlink($file);
                            }
                        }
                    }
                }
            }
        }

        // 3. Convert Uploaded Logos
        $this->newLine();
        $this->info("3. Mengonversi Logo Apotek (public/uploads/logo)...");
        $logoDir = public_path('uploads/logo');
        if (file_exists($logoDir)) {
            $files = glob($logoDir . '/*.*');
            foreach ($files as $file) {
                $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                $filename = basename($file);

                if (in_array($ext, ['png', 'jpg', 'jpeg', 'bmp', 'gif'])) {
                    $originalSize = filesize($file);
                    $newFilename = ImageService::uploadAsWebp($file, $logoDir, 85, 'logo');
                    $newPath = $logoDir . DIRECTORY_SEPARATOR . $newFilename;

                    if (file_exists($newPath) && filesize($newPath) > 0) {
                        $webpSize = filesize($newPath);
                        $totalOriginalBytes += $originalSize;
                        $totalWebpBytes += $webpSize;
                        $convertedCount++;

                        $newLogoUrl = '/uploads/logo/' . $newFilename;
                        $this->line("  ✓ {$filename} (" . round($originalSize / 1024, 1) . " KB) -> {$newFilename} (" . round($webpSize / 1024, 1) . " KB)");

                        if (!$dryRun) {
                            DB::table('settings')->where('key', 'pharmacy_logo')->update(['value' => $newLogoUrl]);

                            if ($newFilename !== $filename && file_exists($file)) {
                                @unlink($file);
                            }
                        }
                    }
                }
            }
        }

        $this->newLine();
        $this->info("=== Ringkasan Hasil Konversi ===");
        $this->info("Total gambar diproses: {$convertedCount}");
        $this->info("Ukuran awal: " . round($totalOriginalBytes / 1024, 1) . " KB");
        $this->info("Ukuran WebP: " . round($totalWebpBytes / 1024, 1) . " KB");

        if ($totalOriginalBytes > 0) {
            $saved = $totalOriginalBytes - $totalWebpBytes;
            $percent = round(($saved / $totalOriginalBytes) * 100, 1);
            $this->info("Penghematan ukuran: " . round($saved / 1024, 1) . " KB ({$percent}% lebih ringan!)");
        }

        return Command::SUCCESS;
    }
}
