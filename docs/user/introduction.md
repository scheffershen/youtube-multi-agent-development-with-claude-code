---
sidebar_position: 1
id: introduction
title: Introduction
---

# TicketFlow

TicketFlow est une API de gestion de demandes d'assistance. Elle permet de créer des
tickets, de suivre leur statut et d'obtenir des statistiques d'ensemble.

Cette documentation s'adresse aux personnes qui utilisent l'API, directement ou via un
tableau de bord. Elle décrit ce que chaque appel fait, les données attendues et les
réponses possibles.

## Organisation

- **Tickets** : création, consultation, changement de statut.
- **Statistiques** : vue d'ensemble des tickets par statut.
- **Annexe** : journal des versions (changelog), flash infos et dossiers de preuves de
  validation.

## Conventions

- Toutes les requêtes et réponses sont au format JSON.
- Les dates sont au format ISO 8601 avec fuseau horaire, par exemple
  `2026-10-14T13:01:02+00:00`.
- Les erreurs sont renvoyées en JSON avec un code HTTP explicite : `404` pour un
  élément introuvable, `400` pour un paramètre invalide, `422` pour des données
  refusées.
