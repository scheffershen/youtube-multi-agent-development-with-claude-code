---
sidebar_position: 1
id: flash-info-2026-10-v020
title: Flash info — v0.2.0
---

Bonjour à toutes et à tous,

La **version 0.2.0** de TicketFlow donne à chaque nouveau ticket une échéance de traitement calculée à partir de sa priorité.

---

## ⏱️ Échéance de traitement des tickets

Jusqu'ici, un ticket portait sa date de création mais aucune date cible de traitement.

Désormais, chaque ticket créé reçoit une échéance (`dueAt`) égale à sa date de création augmentée d'un délai qui dépend de la priorité déclarée : 72 heures pour `low`, 24 heures pour `normal`, 8 heures pour `high`. Toute autre valeur de priorité reçoit le délai de `normal`, soit 24 heures. Cette échéance est fixée une fois pour toutes à la création et n'est jamais recalculée. Aucun nouvel endpoint n'est ajouté.

### Pour les équipes qui utilisent l'API

L'objet ticket renvoyé par `POST /api/tickets`, `GET /api/tickets`, `GET /api/tickets/{id}` et `PATCH /api/tickets/{id}/status` contient deux nouvelles clés, ajoutées en fin d'objet :

- `dueAt` : l'échéance, au format ISO 8601 comme `createdAt` ; `null` pour les tickets créés avant cette version.
- `assignee` : l'agent affecté au ticket ; toujours `null` dans cette version.

Aucune clé n'est renommée ni supprimée, les codes de statut sont inchangés et `GET /api/stats` ne change pas.

### Par défaut, rien ne change

Vous n'avez rien à faire. Les tickets existants sont conservés à l'identique ; ils n'ont pas d'échéance et leur champ `dueAt` vaut `null`. Les appels que vous faites aujourd'hui continuent de fonctionner tels quels.

---

## Support et accompagnement

Pour toute question ou pour signaler un incident, vous pouvez :

- Contacter l'équipe projet
- Déposer une demande dans le dossier `requests/` du dépôt

Bien cordialement,
L'équipe IT
