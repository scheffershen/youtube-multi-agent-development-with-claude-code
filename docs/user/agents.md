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

Les réponses qui décrivent un agent (création, liste) contiennent exactement ces quatre
champs, dans cet ordre.

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
