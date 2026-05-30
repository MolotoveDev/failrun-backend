<?php

namespace App\Service;

use Doctrine\DBAL\Connection;
use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

final readonly class AdminChatService
{
    public function __construct(
        private AgentInterface $agent,
        private Connection $readonlyConnection,
    ) {
    }

    /**
     * @param array<array{role: string, content: string}> $history Conversation history from the frontend.
     */
    public function chat(array $history): string
    {
        $context      = $this->buildContext();
        $systemPrompt = $this->buildSystemPrompt($context);

        $messages = [Message::forSystem($systemPrompt)];

        foreach ($history as $entry) {
            $role    = $entry['role']    ?? 'user';
            $content = $entry['content'] ?? '';

            if ($content === '') {
                continue;
            }

            $messages[] = match ($role) {
                'assistant' => Message::ofAssistant($content),
                default     => Message::ofUser($content),
            };
        }

        $result  = $this->agent->call(new MessageBag(...$messages));
        $content = $result->getContent();

        return is_string($content) ? $content : 'No s\'ha pogut obtenir una resposta vàlida.';
    }

    private function buildContext(): array
    {
        $db = $this->readonlyConnection;

        $totalUsers    = (int) $db->fetchOne('SELECT COUNT(*) FROM user');
        $totalClips    = (int) $db->fetchOne('SELECT COUNT(*) FROM clips');
        $pendingClips  = (int) $db->fetchOne('SELECT COUNT(*) FROM clips WHERE clip_status = 0');
        $approvedClips = (int) $db->fetchOne('SELECT COUNT(*) FROM clips WHERE clip_status = 1');
        $totalGames    = (int) $db->fetchOne('SELECT COUNT(*) FROM games');
        $pendingReqs   = (int) $db->fetchOne('SELECT COUNT(*) FROM user_request WHERE status_request = 0');
        $acceptedReqs  = (int) $db->fetchOne('SELECT COUNT(*) FROM user_request WHERE status_request = 1');
        $rejectedReqs  = (int) $db->fetchOne('SELECT COUNT(*) FROM user_request WHERE status_request = 2');

        $recentPending = $db->fetchAllAssociative(
            'SELECT c.id, c.clip_title, u.username, c.clip_date
             FROM clips c
             LEFT JOIN user u ON c.user_id_id = u.id
             WHERE c.clip_status = 0
             ORDER BY c.clip_date DESC
             LIMIT 5'
        );

        $games = $db->fetchFirstColumn('SELECT game_name FROM games ORDER BY game_name');

        return [
            'total_users'    => $totalUsers,
            'total_clips'    => $totalClips,
            'pending_clips'  => $pendingClips,
            'approved_clips' => $approvedClips,
            'total_games'    => $totalGames,
            'pending_reqs'   => $pendingReqs,
            'accepted_reqs'  => $acceptedReqs,
            'rejected_reqs'  => $rejectedReqs,
            'recent_pending' => $recentPending,
            'games'          => $games,
        ];
    }

    private function buildSystemPrompt(array $ctx): string
    {
        $recentPendingLines = array_map(static fn($r) => sprintf(
            '  - [ID %d] "%s" por %s (%s)',
            $r['id'],
            $r['clip_title'],
            $r['username'] ?? 'desconocido',
            $r['clip_date']
        ), $ctx['recent_pending']);

        $recentPendingText = $recentPendingLines
            ? implode("\n", $recentPendingLines)
            : '  (ninguno)';

        $gameList = $ctx['games']
            ? implode(', ', $ctx['games'])
            : '(sin juegos)';

        return <<<PROMPT
Eres el asistente de administración de Failrun, una plataforma de gaming clips.
Ayudas a los administradores con gestión de usuarios, moderación de clips, revisión de solicitudes y consultas técnicas.
Responde siempre en el idioma del usuario. Sé conciso y útil.

DATOS ACTUALES DE LA PLATAFORMA (solo lectura, extraídos en tiempo real de la base de datos):
- Usuarios registrados: {$ctx['total_users']}
- Clips totales: {$ctx['total_clips']} (aprobados: {$ctx['approved_clips']}, pendientes de revisión: {$ctx['pending_clips']})
- Juegos disponibles: {$ctx['total_games']} → {$gameList}
- Solicitudes de usuarios: {$ctx['pending_reqs']} pendientes / {$ctx['accepted_reqs']} aceptadas / {$ctx['rejected_reqs']} rechazadas

Últimos clips pendientes de revisión (máx. 5):
{$recentPendingText}

RESTRICCIONES IMPORTANTES:
- Solo tienes acceso de lectura a la base de datos. No puedes crear, modificar ni eliminar datos.
- Si el administrador solicita una acción (aprobar/rechazar clip, banear usuario, etc.), indícale que lo haga desde la interfaz del panel.
- No inventes datos ni estadísticas que no estén en el contexto proporcionado.
PROMPT;
    }
}
