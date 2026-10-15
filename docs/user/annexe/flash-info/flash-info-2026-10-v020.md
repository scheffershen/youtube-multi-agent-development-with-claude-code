---
sidebar_position: 0
id: flash-info-2026-10-v020
title: Flash info — v0.2.0
---

Bonjour à toutes et à tous,

La **version 0.2.0** de TicketFlow permet de filtrer la liste des tickets par statut, ajoute la priorité `urgent` avec une répartition par priorité dans les statistiques, et corrige trois cas qui provoquaient une erreur interne : les statistiques sans ticket, la création d'un ticket incomplet et la consultation d'un ticket inexistant.

---

## 🔎 Filtrer la liste des tickets par statut

Jusqu'ici, la liste des tickets renvoyait toujours l'ensemble des tickets, quel que soit leur statut.

Il est désormais possible de ne récupérer que les tickets d'un statut donné : `open`, `in_progress` ou `closed`.

### Comment l'utiliser

- Ajoutez le paramètre `status` à la liste : `GET /api/tickets?status=open`.
- La réponse garde la même forme, `{ "items": [...], "count": n }`, du plus récent au plus ancien.
- Lorsqu'aucun ticket n'a le statut demandé, la réponse est `200` avec une liste vide et `count` à 0 : ce n'est pas une erreur.
- La valeur doit être écrite exactement : `OPEN` est refusé, de même qu'un paramètre vide (`?status=`).

### Par défaut, rien ne change

Sans paramètre `status`, la liste renvoie toujours tous les tickets, comme avant.

### Pour les équipes qui utilisent l'API

`GET /api/tickets` prend désormais en compte le paramètre `status`, qui était jusqu'ici ignoré. Une valeur inconnue ou vide renvoie `400` avec le corps `{ "errors": { "status": "<message>" } }` ; le message indique les valeurs acceptées et la liste n'est pas renvoyée. Si vos appels transmettaient déjà un paramètre `status` sans effet, vérifiez sa valeur.

---

## 🚨 Priorité `urgent` et répartition par priorité

Jusqu'ici, un ticket pouvait être créé avec les priorités `low`, `normal` ou `high`, sans moyen de signaler une demande plus pressante, et n'importe quelle autre valeur était enregistrée telle quelle.

Il est désormais possible de créer un ticket de priorité `urgent`, la plus haute, dans l'ordre `low`, `normal`, `high`, `urgent`. Les statistiques indiquent aussi le nombre de tickets par priorité.

### Comment l'utiliser

- Créez le ticket avec `POST /api/tickets` et `"priority": "urgent"`.
- Consultez la répartition dans `GET /api/stats`, sous la clé `by_priority` : `low`, `normal`, `high`, `urgent`, et `other` pour les tickets dont la priorité n'est pas l'une des quatre valeurs. La somme des cinq valeurs est égale à `total`.

### Ce qui change pour vous

La priorité est désormais contrôlée à la création : seules les valeurs `low`, `normal`, `high` et `urgent` sont acceptées, écrites exactement (`HIGH` est refusé). Une autre valeur, par exemple `critical`, qui était auparavant enregistrée, est maintenant refusée et le ticket n'est pas créé. Si vous utilisiez une priorité personnalisée, remplacez-la par l'une des quatre valeurs acceptées.

Les tickets déjà enregistrés ne sont ni modifiés ni supprimés : un ticket dont la priorité est hors liste reste affiché tel quel dans la liste et à la consultation, et il est compté dans `other`. Les autres compteurs des statistiques ne changent pas.

### Pour les équipes qui utilisent l'API

`POST /api/tickets` renvoie `422` avec le corps `{ "errors": { "priority": "<message>" } }` pour une priorité non vide hors des quatre valeurs acceptées ; `urgent` renvoie `201`. La réponse de `GET /api/stats` gagne la clé `by_priority`, toujours présente ; les clés existantes (`total`, `open`, `in_progress`, `closed`, `closed_ratio`) sont inchangées.

---

## 📊 Statistiques disponibles même sans ticket

Lorsqu'aucun ticket n'existait, les statistiques renvoyaient une erreur au lieu d'afficher des compteurs.

Les statistiques répondent désormais normalement dans ce cas : tous les compteurs valent 0 et la part de tickets clôturés vaut 0. Dès qu'au moins un ticket existe, les valeurs sont calculées comme avant. Aucune donnée n'a été affectée.

### Pour les équipes qui utilisent l'API

`GET /api/stats` sans ticket renvoie `200` au lieu de `500` ; `closed_ratio` vaut alors `0`, un nombre, jamais `null` ni du texte. La forme de la réponse avec des tickets ne change pas.

---

## 🎫 Création d'un ticket incomplet

La création d'un ticket sans titre, sans description ou sans priorité provoquait une erreur interne, sans indiquer ce qui manquait.

La réponse indique désormais chaque champ manquant, tous signalés en une seule fois. Un champ est considéré comme manquant lorsqu'il est absent, `null` ou vide. Aucun ticket n'est créé dans ce cas. Un ticket complet est créé comme avant.

### Pour les équipes qui utilisent l'API

`POST /api/tickets` avec un champ obligatoire manquant renvoie `422` au lieu de `500`, avec le corps `{ "errors": { "<champ>": "<message>" } }`. Un corps `{}` ou vide renvoie les trois clés `title`, `description` et `priority`.

---

## 🔍 Consultation d'un ticket inexistant

La consultation d'un ticket avec un identifiant qui ne correspond à aucun ticket provoquait une erreur interne.

La réponse indique désormais clairement que le ticket n'existe pas, comme le fait déjà le changement de statut d'un ticket inconnu. La consultation d'un ticket existant ne change pas.

### Pour les équipes qui utilisent l'API

`GET /api/tickets/{id}` avec un identifiant inconnu renvoie `404` au lieu de `500`, avec le corps `{ "error": "Ticket not found" }` : la clé `error` est un texte, et non l'objet `errors` utilisé pour les erreurs de champ.

---

## Support et accompagnement

Pour toute question ou pour signaler un incident, vous pouvez :

- Contacter l'équipe projet
- Déposer une demande dans le dossier `requests/` du dépôt

Bien cordialement,
L'équipe IT
