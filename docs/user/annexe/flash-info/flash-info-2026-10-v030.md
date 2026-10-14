---
sidebar_position: 0
id: flash-info-2026-10-v030
title: Flash info — v0.3.0
---

Bonjour à toutes et à tous,

La **version 0.3.0** de TicketFlow permet de filtrer la liste des tickets par statut, corrige trois erreurs de l'API que vous nous aviez signalées, introduit les agents et l'affectation d'un ticket à un agent, ajoute un filtre sur le dépassement d'échéance et publie un rapport SLA par priorité.

---

## 🔎 Filtre par statut sur la liste des tickets

Jusqu'ici, `GET /api/tickets` renvoyait toujours la liste complète ; le paramètre `status`, s'il était envoyé, était ignoré.

Il est désormais possible de ne demander que les tickets d'un statut donné.

### Comment l'utiliser

Ajoutez le paramètre `status` à la liste : `GET /api/tickets?status=open`. Les valeurs acceptées sont `open`, `in_progress` et `closed`, écrites exactement ainsi (`Open` ou `OPEN` ne sont pas reconnus). La réponse garde la même forme (`items` et `count`), le même ordre du plus récent au plus ancien et les mêmes champs par ticket ; `count` est le nombre de tickets renvoyés. Sans ticket correspondant, vous recevez `200` avec `{ "items": [], "count": 0 }`.

### Pour les équipes qui utilisent l'API

Une valeur de `status` inconnue (vide, casse différente, `done`, ou une valeur non scalaire comme `status[]=open`) renvoie désormais `400` avec exactement `{ "error": "Invalid status", "allowed": ["open", "in_progress", "closed"] }`. Auparavant, ce paramètre était ignoré et la liste complète renvoyée en `200`. Les réponses `200` ne changent pas de forme ; `POST /api/tickets`, `GET /api/tickets/{id}`, `PATCH /api/tickets/{id}/status` et `GET /api/stats` ne sont pas modifiés.

### Par défaut, rien ne change

Sans le paramètre `status`, la liste est identique à celle d'aujourd'hui. Vous n'avez rien à faire.

---

## 🛠️ Trois erreurs de l'API corrigées

Trois situations renvoyaient une erreur `500` au lieu d'une réponse utilisable :

- `POST /api/tickets` avec un corps auquel il manquait `title`, `description` ou `priority` échouait en `500`, et n'importe quelle valeur de priorité (par exemple `urgent`) était acceptée et enregistrée. Le corps est désormais contrôlé : les trois champs sont obligatoires et non vides, le titre est limité à 120 caractères et `priority` doit être exactement `low`, `normal` ou `high`. Un corps invalide renvoie `422` avec `{ "errors": { "<champ>": "<message>" } }`, une clé par champ fautif, et aucun ticket n'est créé.
- `GET /api/tickets/{id}` avec un identifiant inconnu (y compris `0`) renvoyait `500`. Il renvoie désormais `404` avec exactement `{ "error": "Ticket not found" }`, comme le changement de statut et l'affectation.
- `GET /api/stats` sans aucun ticket renvoyait `500`, ce qui laissait le tableau de bord vide (demande 003). Il renvoie désormais `200` avec les quatre compteurs à `0` et `closed_ratio` à `0` ; avec des tickets, les cinq clés, leur ordre et l'arrondi sont inchangés.

Les tickets déjà enregistrés avec une priorité hors de la liste sont conservés et restent renvoyés tels quels, avec leur priorité d'origine, par la liste et la consultation. La priorité `urgent` demandée (demande 002) n'est pas ajoutée dans cette version : elle devra d'abord recevoir un délai cible propre, et la demande reste ouverte.

### Pour les équipes qui utilisent l'API

`POST /api/tickets` renvoie désormais `422` `{ "errors": { "priority": "<message>" } }` pour toute priorité hors de `low`, `normal`, `high` (notamment `urgent`), là où la valeur était auparavant acceptée avec un `201`. Un corps incomplet renvoie `422`, un corps non JSON ou vide `400`, un `Content-Type` autre que JSON `415` ; ces trois codes remplacent un `500`. Les messages de validation sont en anglais. `GET /api/tickets/{id}` inconnu renvoie `404` au lieu de `500` ; `GET /api/stats` sans ticket renvoie `200` au lieu de `500`. Aucune clé n'est ajoutée, renommée ou supprimée sur les réponses `200` et `201`.

---

## 👤 Agents

Jusqu'ici, TicketFlow ne connaissait pas les personnes qui traitent les tickets : le champ `assignee` d'un ticket valait toujours `null`.

Il est désormais possible de créer des agents, de les lister, et de les désactiver ou de les réactiver.

### Comment l'utiliser

- Créez un agent avec `POST /api/agents` et un corps `{ "name": "Ada", "email": "ada@example.test" }`. L'agent est actif par défaut (`"active": true`) ; envoyez `"active": false` pour le créer inactif. L'adresse e-mail est unique : une adresse déjà utilisée est refusée (`422`) et l'agent existant est conservé. Le nom et l'adresse sont enregistrés tels qu'envoyés.
- Retrouvez vos agents avec `GET /api/agents` : `{ "items": [...], "count": n }`, agents actifs d'abord, puis agents inactifs, chaque groupe par ordre de création.
- Désactivez ou réactivez un agent avec `PATCH /api/agents/{id}` et un corps `{ "active": false }` ou `{ "active": true }`. C'est la seule modification possible après la création : le nom et l'adresse ne sont jamais modifiés par cette requête. Désactiver un agent ne retire pas les tickets qui lui sont affectés.

Chaque changement d'activité accepté est consigné dans le journal de l'application avec l'identifiant de l'agent et la nouvelle valeur.

### Pour les équipes qui utilisent l'API

Trois nouvelles routes : `GET /api/agents` (`200`), `POST /api/agents` (`201`, `400`, `415`, `422`) et `PATCH /api/agents/{id}` (`200`, `400`, `404`, `415`, `422`). L'objet agent a exactement quatre clés, dans cet ordre : `id`, `name`, `email`, `active`. Les erreurs de validation sont renvoyées sous la forme `{ "errors": { "<champ>": "<message>" } }`, une clé par champ fautif, messages en anglais ; un agent inconnu renvoie `404` `{ "error": "Agent not found" }`.

Deux effets de bord : une route inconnue sous `/api/` renvoie désormais son `404` en JSON (`{ "error": "<message>" }`) au lieu d'une page HTML, et une méthode autre que `PATCH` sur `/api/agents/{id}` renvoie `405` (en-tête `Allow: PATCH`) au lieu de `404`. Aucun code de statut ni forme de réponse des endpoints existants ne change.

### Par défaut, rien ne change

Vous n'avez rien à faire : tant qu'aucun agent n'est créé, les tickets se comportent comme aujourd'hui.

---

## 🙋 Affectation d'un ticket à un agent

Jusqu'ici, le champ `assignee` renvoyé avec chaque ticket valait toujours `null` et aucun endpoint ne permettait de le renseigner.

Il est désormais possible d'affecter un ticket à un agent actif, de le réaffecter ou de retirer l'affectation ; chaque réponse qui décrit un ticket indique l'agent affecté.

### Comment l'utiliser

- Affectez un agent avec `PATCH /api/tickets/{id}/assignee` et le corps `{ "agentId": 1 }`. L'agent doit exister et être actif. La réponse est `200` avec le ticket complet, dont `assignee` vaut `{ "id": 1, "name": "Ada" }`. Si le ticket était affecté à un autre agent, celui-ci est remplacé ; affecter de nouveau l'agent déjà en place renvoie le même `200`.
- Retirez l'affectation avec `{ "agentId": null }` : la réponse est `200` avec `"assignee": null`, y compris si le ticket n'avait pas d'agent.
- Un corps sans la clé `agentId` (par exemple `{}`) ne désaffecte pas : il est refusé (`422`).

Un agent désactivé après son affectation reste visible comme agent affecté. Chaque affectation ou désaffectation réussie est consignée dans le journal de l'application avec l'identifiant du ticket et celui de l'agent.

### Pour les équipes qui utilisent l'API

Nouvelle route `PATCH /api/tickets/{id}/assignee` (`200`, `400`, `404`, `415`, `422`). Ticket inconnu : `404` `{ "error": "Ticket not found" }` ; agent inconnu : `404` `{ "error": "Agent not found" }` ; agent inactif : `422` `{ "errors": { "agentId": "Agent is not active." } }` ; `agentId` absent ou ni entier ni `null` : `422` `{ "errors": { "agentId": "<message>" } }`.

Sur toutes les réponses qui contiennent un objet ticket (`GET /api/tickets`, `GET /api/tickets/{id}`, `PATCH /api/tickets/{id}/status` et la nouvelle route), la clé `assignee` passe de `null` systématique à `null` ou `{ "id": <entier>, "name": "<nom>" }`, ces deux clés seulement, sans `email` ni `active`. `POST /api/tickets` renvoie toujours `null`. Aucune clé n'est ajoutée, renommée ni supprimée et aucun code de statut existant ne change.

### Par défaut, rien ne change

Les tickets existants ne sont pas affectés : leur champ `assignee` reste `null` tant que vous n'affectez personne.

---

## ⏰ Filtre sur le dépassement d'échéance

Jusqu'ici, la liste des tickets ne permettait pas d'isoler ceux dont l'échéance `dueAt` était dépassée ; le paramètre `breached`, s'il était envoyé, était ignoré.

Il est désormais possible de ne demander que les tickets en dépassement d'échéance, ou au contraire ceux qui ne le sont pas.

### Comment l'utiliser

Ajoutez le paramètre `breached` : `GET /api/tickets?breached=true` renvoie les tickets en dépassement, `GET /api/tickets?breached=false` tous les autres. Seules les valeurs `true` et `false`, écrites exactement ainsi, sont acceptées.

Un ticket est en dépassement d'échéance lorsque, au moment où la requête est traitée, son statut n'est pas `closed`, son champ `dueAt` n'est pas `null` et cette échéance est strictement antérieure à l'heure du serveur. Un ticket `closed` n'est donc jamais en dépassement, quelle que soit son échéance ; un ticket sans échéance (créé avant la version 0.2.0) ne l'est jamais non plus ; une échéance qui tombe exactement à l'heure courante n'est pas dépassée. À un instant donné, les listes `breached=true` et `breached=false` se complètent exactement.

Les deux filtres se combinent, dans n'importe quel ordre : `GET /api/tickets?status=open&breached=true` renvoie les tickets `open` en dépassement, et `GET /api/tickets?status=closed&breached=true` renvoie toujours `{ "items": [], "count": 0 }`. La réponse garde la même forme, le même ordre et les mêmes champs par ticket ; aucun champ `breached` n'est ajouté à l'objet ticket.

### Pour les équipes qui utilisent l'API

Une valeur de `breached` autre que `true` ou `false` (`1`, `0`, `True`, `TRUE`, `yes`, `no`, vide, ou une valeur non scalaire comme `breached[]=true`) renvoie désormais `400` avec exactement `{ "error": "Invalid breached", "allowed": ["true", "false"] }` ; auparavant ce paramètre était ignoré et la liste complète, ou filtrée par `status`, renvoyée en `200`. `status` est contrôlé avant `breached` : si les deux valeurs sont invalides, vous recevez l'erreur de `status`. Les réponses `200` ne changent pas de forme ; les autres endpoints ne sont pas modifiés.

### Par défaut, rien ne change

Sans le paramètre `breached`, la liste est identique à celle d'aujourd'hui, avec ou sans `status`.

---

## 📈 Rapport SLA par priorité

Jusqu'ici, `GET /api/stats` donnait le nombre de tickets par statut, mais aucun rapport ne mesurait le respect des échéances.

Il est désormais possible d'obtenir, pour chaque priorité, le nombre de tickets, le nombre de tickets en dépassement d'échéance et le taux de dépassement.

### Comment l'utiliser

Appelez `GET /api/reports/sla`, sans paramètre. La réponse contient `generatedAt`, l'instant d'évaluation au même format que `createdAt` et `dueAt`, puis `priorities`, une liste avec une entrée par priorité, toujours dans l'ordre `low`, `normal`, `high`, même sans ticket :

```json
{ "priority": "normal", "total": 3, "breached": 1, "breach_rate": 33.3 }
```

`total` compte tous les tickets de la priorité, quel que soit leur statut ; `breached` ceux qui sont en dépassement d'échéance à l'instant `generatedAt`, selon la même définition que le filtre `breached` de la liste ; `breach_rate` vaut `breached` divisé par `total`, multiplié par 100 et arrondi à une décimale, et `0.0` lorsque la priorité n'a aucun ticket. Un seul instant sert à toutes les priorités. Les tickets dont la priorité n'est pas `low`, `normal` ou `high` sont regroupés dans une entrée `other`, placée en dernier et présente seulement si elle compte au moins un ticket.

### Pour les équipes qui utilisent l'API

Nouvelle route `GET /api/reports/sla` (`200` ; `405` pour les autres méthodes, avec l'en-tête `Allow: GET`). La réponse a exactement deux clés, `generatedAt` et `priorities`, et chaque entrée exactement quatre clés, dans cet ordre : `priority`, `total`, `breached`, `breach_rate`. `breach_rate` est toujours un nombre JSON avec partie décimale (`0.0`, `50.0`, `100.0`, jamais `0` ni `"50.0"`). Toute chaîne de requête est ignorée. `GET /api/stats` conserve exactement ses cinq clés et son comportement ; aucune réponse existante n'est modifiée.

### Par défaut, rien ne change

Le rapport s'ajoute aux statistiques existantes sans les modifier. Vous n'avez rien à faire.

---

## Support et accompagnement

Pour toute question ou pour signaler un incident, vous pouvez :

- Contacter l'équipe projet
- Déposer une demande dans le dossier `requests/` du dépôt

Bien cordialement,
L'équipe IT
