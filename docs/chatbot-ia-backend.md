# Chatbot IA amb context de base de dades

## Visió general

El chatbot del panell d'administració de Failrun utilitza un model de llenguatge (LLM) allotjat als servidors de l'institut. En lloc de respondre preguntes genèriques, el model rep en cada petició un resum actualitzat de la base de dades (usuaris, clips, jocs, sol·licituds), de manera que pot respondre preguntes concretes sobre la plataforma en temps real.

L'accés a la base de dades és estrictament de **només lectura**: el model mai toca directament les dades, sinó que rep un text descriptiu preparat pel backend.

---

## Paquets instal·lats

S'han afegit tres paquets de l'ecosistema oficial de Symfony AI:

```
symfony/ai-bundle          → bundle principal: registra l'agent i el configura com a servei
symfony/ai-platform        → capa base de comunicació amb la plataforma d'IA
symfony/ai-generic-platform → pont per a APIs compatibles amb OpenAI (com Ollama)
symfony/ai-agent           → proporciona AgentInterface i la lògica d'enviament de missatges
```

---

## Configuració del model (`config/packages/ai.yaml`)

```yaml
ai:
    platform:
        generic:
            llama:
                base_url: '%env(AI_BASE_URL)%'
                api_key: '%env(AI_API_KEY)%'
                model_catalog: 'Symfony\AI\Platform\Bridge\Generic\FallbackModelCatalog'
    agent:
        default:
            platform: 'ai.platform.generic.llama'
            model: '%env(AI_MODEL)%'
```

Aquí es declara la **plataforma** (el servidor on viu el model) i l'**agent** (la instància que s'usarà a l'aplicació).

- `base_url`, `api_key` i `model` es llegeixen de variables d'entorn per no posar credencials al codi.
- `FallbackModelCatalog` s'usa perquè el servidor no és OpenAI sinó un model local (Ollama), i aquest catàleg accepta qualsevol nom de model sense necessitat de tenir-lo predefinit.
- El bundle crea automàticament el servei `ai.agent.default` que usarem a tot arreu.

Les variables d'entorn necessàries (definides a `.env.local`) són:

```
AI_BASE_URL=https://spark2-ia.institutmontilivi.cat/ollama
AI_API_KEY=sk-...
AI_MODEL=llama3.1:70b
```

> La ruta base és `/ollama` perquè el servidor exposa el model a través del prefix `/ollama/v1/chat/completions`, que és l'endpoint compatible amb OpenAI d'Ollama.

---

## Connexió de base de dades de només lectura (`config/packages/doctrine.yaml`)

S'ha afegit una **segona connexió DBAL** independent de la connexió principal:

```yaml
doctrine:
    dbal:
        default_connection: default
        connections:
            default:
                url: '%env(resolve:DATABASE_URL)%'
            ai_readonly:
                url: '%env(resolve:DATABASE_AI_READONLY_URL)%'
```

L'aplicació té ara dues connexions a MySQL:

| Connexió | Usuari | Ús |
|---|---|---|
| `default` | `failrun-admin` | Tot el backend (lectura i escriptura) |
| `ai_readonly` | `failrun_ai_reader` | Exclusivament el chatbot (només SELECT) |

El servei de la connexió de lectura es diu `doctrine.dbal.ai_readonly_connection` i l'injecta Symfony automàticament.

---

## El servei principal (`src/Service/AdminChatService.php`)

Aquest servei és el nucli de tot. Fa tres coses:

### 1. Rep l'historial de la conversa

```php
public function chat(array $history): string
```

El frontend envia un array de missatges `[{role: "user", content: "..."}, ...]`. El servei els converteix en objectes `Message` de Symfony AI i els afegeix al `MessageBag` que s'enviarà al model.

### 2. Llegeix la base de dades i construeix el context

```php
private function buildContext(): array
```

Abans de cridar al model, es fan diverses consultes SQL de només lectura sobre la connexió `ai_readonly`:

- Recomptes totals: usuaris, clips (aprovats i pendents), jocs, sol·licituds.
- Llistat dels 5 clips pendents de revisió més recents, amb títol, autor i data.
- Llistat dels noms de jocs disponibles.

Totes les consultes són `SELECT` simples. La connexió `ai_readonly` no pot fer `INSERT`, `UPDATE` ni `DELETE` a nivell de MySQL.

### 3. Construeix el system prompt i crida al model

```php
private function buildSystemPrompt(array $ctx): string
```

Les dades llegides de la base de dades s'insereixen dins d'un **system prompt**: un missatge especial que el model rep al principi de la conversa i que defineix el seu comportament i context. Exemple simplificat del que rep el model:

```
Eres el asistente de administración de Failrun...

DATOS ACTUALES DE LA PLATAFORMA:
- Usuarios registrados: 42
- Clips totales: 138 (aprobados: 120, pendientes: 18)
- Juegos disponibles: 5 → Fortnite, Minecraft, ...
- Solicitudes: 3 pendientes / 10 aceptadas / 2 rechazadas

Últimos clips pendientes:
  - [ID 74] "Epic fail" por joan123 (2026-05-28)
  ...

RESTRICCIONES: Solo lectura. No inventes datos.
```

Amb aquest context, el model pot respondre preguntes com "quants usuaris tenim?" o "quins clips estan pendents?" amb dades reals i actuals.

Finalment es crida a l'agent:

```php
$result = $this->agent->call(new MessageBag(...$messages));
```

`MessageBag` agrupa el system prompt + tot l'historial de la conversa. L'`AgentInterface` s'encarrega d'enviar-ho al model i retornar la resposta en text pla.

---

## Registre del servei (`config/services.yaml`)

```yaml
services:
    _defaults:
        bind:
            Symfony\AI\Agent\AgentInterface: '@ai.agent.default'

    Symfony\AI\Platform\Bridge\Generic\FallbackModelCatalog: ~

    App\Service\AdminChatService:
        arguments:
            $readonlyConnection: '@doctrine.dbal.ai_readonly_connection'
```

Tres coses rellevants:

- El **binding global** de `AgentInterface` fa que qualsevol servei que demani `AgentInterface` al constructor rebi automàticament `ai.agent.default` (l'agent configurat a `ai.yaml`).
- `FallbackModelCatalog` es registra com a servei perquè Symfony el pugui injectar quan la plataforma el necessiti.
- `AdminChatService` rep explícitament la connexió `ai_readonly` (no la connexió principal), garantint que el chatbot mai pugui escriure a la base de dades.

---

## El controlador (`src/Controller/FailrunAdminPanelController.php`)

L'endpoint del chatbot ha quedat molt simplificat:

```php
#[Route('/failrun/admin/chatbot', methods: ['POST'])]
public function chatbot(Request $request, AdminChatService $chatService): JsonResponse
{
    if (!$this->isGranted('ROLE_ADMIN') && !$this->isGranted('ROLE_MODERATOR')) {
        return $this->json(['error' => 'Acceso denegado'], 403);
    }

    $data     = json_decode($request->getContent(), true);
    $messages = $data['messages'] ?? [];

    $reply = $chatService->chat($messages);
    return $this->json(['reply' => $reply]);
}
```

El controlador únicament comprova que l'usuari és admin o moderador, extreu els missatges del cos de la petició i delega tota la lògica al `AdminChatService`. La resposta és un JSON `{ "reply": "..." }` igual que abans, de manera que el frontend no ha de canviar res.

---

## Flux complet d'una petició

```
Frontend (admin panel)
    │
    │  POST /failrun/admin/chatbot
    │  { messages: [{role:"user", content:"quants clips pendents hi ha?"}] }
    ▼
FailrunAdminPanelController
    │  comprova ROLE_ADMIN / ROLE_MODERATOR
    ▼
AdminChatService::chat()
    │
    ├── buildContext()  →  consultes SELECT a MySQL (ai_readonly)
    │                      obté stats en temps real
    │
    ├── buildSystemPrompt()  →  insereix les dades al system prompt
    │
    └── agent->call(MessageBag)
            │
            │  POST https://spark2-ia.../ollama/v1/chat/completions
            │  { model: "llama3.1:70b", messages: [system, user] }
            ▼
        Model LLM (Ollama, institut)
            │
            │  "Hay 18 clips pendientes de revisión..."
            ▼
        JSON { "reply": "Hay 18 clips pendientes..." }
            ▼
        Frontend
```
