---
sidebar_position: 0
id: flash-info-2026-10-v010
title: Flash info — v0.1.0
---

Bonjour à toutes et à tous,

La **version 0.1.0** de TicketFlow ouvre l'API de gestion des demandes d'assistance : vous pouvez désormais créer des tickets, suivre leur statut et consulter une vue d'ensemble.

---

## 🎫 Tickets

Jusqu'ici, les demandes d'assistance étaient suivies en dehors de l'outil.

Il est désormais possible de créer un ticket avec un titre, une description et une priorité (`low`, `normal`, `high`), de le consulter, et de faire évoluer son statut entre `open`, `in_progress` et `closed`.

### Comment l'utiliser

- Créez un ticket avec `POST /api/tickets`.
- Retrouvez-le avec `GET /api/tickets/{id}` ou dans la liste `GET /api/tickets`.
- Changez son statut avec `PATCH /api/tickets/{id}/status`.

---

## 📊 Statistiques

`GET /api/stats` renvoie le nombre de tickets par statut et la part de tickets clôturés, pour alimenter un tableau de bord.

---

## Support et accompagnement

Pour toute question ou pour signaler un incident, vous pouvez :

- Contacter l'équipe projet
- Déposer une demande dans le dossier `requests/` du dépôt

Bien cordialement,
L'équipe IT
