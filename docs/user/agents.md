---
sidebar_position: 4
id: agents
title: Agents
---

# Agents

Un agent porte un nom, une adresse e-mail et un indicateur d'activité. L'adresse e-mail
est unique : deux agents ne peuvent pas partager la même adresse.

## Champs d'un agent

| Champ | Type | Description |
|-------|------|-------------|
| `id` | entier | Identifiant attribué à la création |
| `name` | texte (120 caractères max.) | Nom de l'agent |
| `email` | texte (180 caractères max.) | Adresse e-mail, unique parmi les agents |
| `active` | booléen | `true` si l'agent est actif, `false` sinon ; `true` par défaut |

Les réponses qui décrivent un agent (création, liste, changement d'activité) contiennent
exactement ces quatre champs, dans cet ordre.

## Créer un agent

`POST /api/agents`

La requête est envoyée avec l'en-tête `Content-Type: application/json`.

| Champ | Type | Obligatoire | Description |
|-------|------|-------------|-------------|
| `name` | texte | oui | Nom de l'agent ; ni vide ni composé uniquement d'espaces, 120 caractères au plus |
| `email` | texte | oui | Adresse e-mail syntaxiquement valide, ni vide ni composée uniquement d'espaces, 180 caractères au plus |
| `active` | booléen | non | Indicateur d'activité ; `true` si absent |

Corps attendu :

```json
{ "name": "Ada", "email": "ada@example.test" }
```

Réponse `201` avec l'agent créé :

```json
{ "id": 1, "name": "Ada", "email": "ada@example.test", "active": true }
```

Le nom et l'adresse e-mail sont enregistrés tels qu'envoyés : aucun espace n'est
retiré et la casse est conservée. Envoyer `"active": false` crée un agent inactif. Les
clés inconnues du corps sont ignorées. L'agent créé apparaît ensuite dans la liste des
agents.

### Erreurs

Toutes les erreurs sont renvoyées en JSON. Lorsqu'une requête est refusée, aucun agent
n'est créé.

| Code | Signification |
|------|---------------|
| `422` | Un ou plusieurs champs sont invalides (`name` ou `email` manquant, `null`, d'un autre type que texte, vide, composé uniquement d'espaces ou trop long ; `email` qui n'est pas une adresse valide, par exemple `not-an-email` ou `ada@` ; `active` présent mais qui n'est pas un booléen, par exemple `"yes"`). La réponse est `{ "errors": { "<champ>": "<message>" } }` : une clé par champ fautif (`name`, `email` ou `active`), avec le premier message d'erreur de ce champ |
| `422` | L'adresse e-mail est déjà utilisée par un agent existant. La réponse est exactement `{ "errors": { "email": "An agent with this email already exists." } }` et l'agent existant est conservé |
| `400` | Le corps n'est pas du JSON valide ou est vide ; la réponse est `{ "error": "<message>" }` |
| `415` | Le corps est envoyé avec un `Content-Type` autre que JSON, par exemple `text/plain` ; la réponse est `{ "error": "<message>" }` |

La comparaison des adresses e-mail est exacte et sensible à la casse : `dup@example.test`
et `Dup@example.test` sont deux adresses distinctes et peuvent chacune être attribuées à
un agent.

## Lister les agents

`GET /api/agents`

Renvoie `{ "items": [...], "count": n }`, où `count` est le nombre d'agents renvoyés et
chaque élément de `items` a les champs décrits ci-dessus.

Les agents actifs sont listés en premier, puis les agents inactifs ; au sein de chaque
groupe, les agents apparaissent par identifiant croissant, c'est-à-dire dans l'ordre de
leur création. L'ordre est donc toujours le même pour un même ensemble d'agents.

Sans agent, la réponse est `200` avec exactement `{ "items": [], "count": 0 }`.

Les paramètres de requête sont ignorés : la liste ne propose ni filtre ni pagination.

## Changer l'indicateur d'activité

`PATCH /api/agents/{id}`

La requête est envoyée avec l'en-tête `Content-Type: application/json`. `{id}` est
l'identifiant numérique de l'agent.

| Champ | Type | Obligatoire | Description |
|-------|------|-------------|-------------|
| `active` | booléen | oui | Nouvelle valeur de l'indicateur d'activité : `false` pour désactiver l'agent, `true` pour le réactiver |

Corps attendu :

```json
{ "active": false }
```

Réponse `200` avec l'agent mis à jour, dans la même forme qu'à la création :

```json
{ "id": 1, "name": "Ada", "email": "ada@example.test", "active": false }
```

Le nom et l'adresse e-mail ne sont jamais modifiés par cette requête. Les clés autres
que `active` sont ignorées, y compris `name`, `email` et `id` : les envoyer ne change
rien. Envoyer la valeur que l'agent possède déjà est accepté et renvoie `200` avec le
même corps.

La modification est enregistrée : la liste des agents (`GET /api/agents`) affiche
ensuite la nouvelle valeur et son ordre (agents actifs d'abord, puis agents inactifs) en
tient compte.

Désactiver un agent ne retire pas les tickets qui lui sont affectés : ils restent
affectés à cet agent (voir [Agent affecté](./tickets.md#agent-affecté)).

Chaque changement accepté (réponse `200`, y compris lorsque la valeur est inchangée) est
consigné dans le journal de l'application avec l'identifiant de l'agent et la nouvelle
valeur. Les requêtes refusées ne le sont pas.

Seule la méthode `PATCH` est disponible sur `/api/agents/{id}` : il n'existe pas de
consultation d'un agent par identifiant.

### Erreurs

Toutes les erreurs sont renvoyées en JSON. Lorsqu'une requête est refusée, l'indicateur
d'activité de l'agent est conservé.

| Code | Signification |
|------|---------------|
| `422` | La clé `active` est absente du corps (par exemple `{}`) ou n'est pas un booléen (`null`, un texte tel que `"yes"`, `"true"` ou `""`, un nombre tel que `1` ou `0`, un tableau). La réponse est `{ "errors": { "active": "<message>" } }`, `active` étant la seule clé de `errors` |
| `404` | Aucun agent ne porte l'identifiant demandé ; la réponse est exactement `{ "error": "Agent not found" }` |
| `404` | L'identifiant n'est pas numérique, par exemple `/api/agents/abc` ; la réponse est `{ "error": "<message>" }` |
| `405` | La méthode n'est pas `PATCH`, par exemple `GET /api/agents/1` ; la réponse est `{ "error": "<message>" }` et l'en-tête `Allow` indique `PATCH` |
| `400` | Le corps n'est pas du JSON valide ou est vide ; la réponse est `{ "error": "<message>" }` |
| `415` | Le corps est envoyé avec un `Content-Type` autre que JSON, par exemple `text/plain` ; la réponse est `{ "error": "<message>" }` |

Le corps est contrôlé avant la recherche de l'agent : un identifiant inconnu accompagné
d'un corps invalide renvoie `422`, et non `404`.
