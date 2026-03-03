<?php

namespace App\Services;

use App\Models\Notification;

/**
 * Service : Création de notifications en base de données.
 * Les notifications push/email/SMS sont déclenchées via Events/Listeners séparément.
 */
class NotificationService
{
    /**
     * Crée une notification pour un utilisateur.
     *
     * @param int    $userId  ID de l'utilisateur destinataire
     * @param string $titre
     * @param string $message
     * @param string $type    new_order|payment|delivery|review|system|subscription
     * @param array  $data    Données JSON optionnelles
     */
    public function notifier(
        int $userId,
        string $titre,
        string $message,
        string $type = 'system',
        array $data = []
    ): Notification {
        return Notification::create([
            'user_id' => $userId,
            'titre'   => $titre,
            'message' => $message,
            'type'    => $type,
            'data'    => $data,
        ]);
    }

    /**
     * Notifie plusieurs utilisateurs en même temps.
     *
     * @param array  $userIds
     * @param string $titre
     * @param string $message
     * @param string $type
     * @param array  $data
     */
    public function notifierMultiple(
        array $userIds,
        string $titre,
        string $message,
        string $type = 'system',
        array $data = []
    ): void {
        $now = now();
        $records = array_map(fn($id) => [
            'user_id'    => $id,
            'titre'      => $titre,
            'message'    => $message,
            'type'       => $type,
            'data'       => json_encode($data),
            'is_read'    => false,
            'created_at' => $now,
            'updated_at' => $now,
        ], $userIds);

        Notification::insert($records);
    }
}