<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Service : Gestion des uploads de fichiers vers Cloudflare R2 (compatible S3).
 * Utilisé pour les avatars utilisateurs et les photos de stocks.
 *
 * Fichier : app/Services/StorageService.php
 *
 * Configuration requise dans .env :
 *   AWS_ACCESS_KEY_ID, AWS_SECRET_ACCESS_KEY, AWS_BUCKET,
 *   AWS_ENDPOINT (URL R2), AWS_USE_PATH_STYLE_ENDPOINT=true
 *
 * Disk configuré dans config/filesystems.php : 'r2'
 */
class StorageService
{
    /**
     * Disk par défaut — 'r2' en prod, 'public' en local/test.
     */
    private string $disk;

    public function __construct()
    {
        $this->disk = config('filesystems.default_upload_disk', 'public');
    }

    /**
     * Upload un avatar utilisateur.
     * Génère un nom unique, redimensionne si nécessaire (future amélioration).
     * Supprime l'ancien avatar si existant.
     *
     * @param  UploadedFile $file       Fichier uploadé depuis la requête
     * @param  string|null  $oldAvatar  URL de l'ancien avatar à supprimer
     * @return string                   URL publique du nouvel avatar
     */
    public function uploadAvatar(UploadedFile $file, ?string $oldAvatar = null): string
    {
        // Supprimer l'ancien avatar si ce n'est pas un avatar externe (ex: Google)
        if ($oldAvatar && $this->isLocalFile($oldAvatar)) {
            $this->deleteByUrl($oldAvatar);
        }

        // Générer un nom unique pour éviter les collisions
        $filename  = 'avatar_' . Str::uuid() . '.' . $file->getClientOriginalExtension();
        $directory = 'avatars';

        // Upload sur le disk configuré
        $path = Storage::disk($this->disk)->putFileAs($directory, $file, $filename, 'public');

        return Storage::disk($this->disk)->url($path);
    }

    /**
     * Upload une photo de stock.
     *
     * @param  UploadedFile $file
     * @param  int          $eleveurId  Pour organiser les fichiers par éleveur
     * @return string                   URL publique
     */
    public function uploadStockPhoto(UploadedFile $file, int $eleveurId): string
    {
        $filename  = 'stock_' . Str::uuid() . '.' . $file->getClientOriginalExtension();
        $directory = "stocks/{$eleveurId}";

        $path = Storage::disk($this->disk)->putFileAs($directory, $file, $filename, 'public');

        return Storage::disk($this->disk)->url($path);
    }

    /**
     * Supprime un fichier par son URL publique.
     *
     * @param  string $url  URL complète du fichier
     * @return bool
     */
    public function deleteByUrl(string $url): bool
    {
        // Extraire le chemin relatif depuis l'URL complète
        $baseUrl = Storage::disk($this->disk)->url('');
        $path    = ltrim(str_replace($baseUrl, '', $url), '/');

        if (Storage::disk($this->disk)->exists($path)) {
            return Storage::disk($this->disk)->delete($path);
        }

        return false;
    }

    /**
     * Vérifie si une URL appartient au storage local (pas une URL externe).
     *
     * @param  string $url
     * @return bool
     */
    private function isLocalFile(string $url): bool
    {
        $appUrl = config('app.url');
        $r2Url  = config('aws.endpoint', '');

        return str_starts_with($url, $appUrl) || str_starts_with($url, $r2Url);
    }
}