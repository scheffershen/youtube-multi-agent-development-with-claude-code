---
sidebar_position: 0
id: flash-info-2026-10-v040
title: Flash info — v0.4.0
---

Bonjour à toutes et à tous,

La **version 0.4.0** de TicketFlow ajoute la priorité `urgent`, avec une échéance de traitement de 2 heures, et l'intègre au rapport SLA par priorité.

---

## 🚨 Priorité `urgent` avec une échéance de 2 heures

Jusqu'ici, un ticket ne pouvait être créé qu'avec la priorité `low`, `normal` ou `high` ; la valeur `urgent`, demandée dans la demande 002, était refusée depuis la version 0.3.0 parce qu'elle n'avait pas encore de délai cible propre.

Il est désormais possible de créer un ticket avec la priorité `urgent`. Elle se place au-dessus de `high` et donne au ticket une échéance (`dueAt`) de 2 heures après sa création. Le rapport SLA par priorité compte ces tickets dans une ligne `urgent` qui leur est propre.

### Comment l'utiliser

Créez le ticket comme d'habitude avec `POST /api/tickets` et la valeur `urgent` dans `priority` :

```json
{ "title": "Serveur de production injoignable", "description": "Plus aucune réponse depuis 9 h 10.", "priority": "urgent" }
```

La réponse est `201` avec le ticket habituel : `priority` à `"urgent"`, `status` à `open`, `assignee` à `null` et `dueAt` égal à `createdAt` plus 2 heures. Les délais cibles des autres priorités ne changent pas : 72 heures pour `low`, 24 heures pour `normal`, 8 heures pour `high`. Comme pour les autres valeurs, l'écriture doit être exacte : `Urgent` ou `URGENT` sont refusés.

Un ticket `urgent` dont l'échéance est dépassée apparaît dans `GET /api/tickets?breached=true`, et `GET /api/reports/sla` renvoie maintenant quatre lignes, dans l'ordre `low`, `normal`, `high`, `urgent` ; la ligne `urgent` est toujours présente, même sans ticket, et calculée avec les mêmes règles que les trois autres. L'entrée `other` ne regroupe plus que les tickets dont la priorité n'est aucune de ces quatre valeurs.

### Pour les équipes qui utilisent l'API

Ce changement revient sur un point annoncé dans la lettre de la version 0.3.0 : `POST /api/tickets` avec `"priority": "urgent"` renvoie désormais `201` et crée le ticket, là où la version 0.3.0 renvoyait `422`. Si vos appels se fiaient à ce refus, ils ne l'obtiendront plus. Toute autre valeur hors de `low`, `normal`, `high`, `urgent` (y compris une casse différente, `critical`, une chaîne vide, `null`, un nombre ou une clé absente) renvoie toujours `422` avec `{ "errors": { "priority": "<message>" } }`, et rien n'est enregistré.

`GET /api/reports/sla` renvoie quatre lignes de priorité au lieu de trois, `urgent` en quatrième position et toujours présente ; les tickets `urgent` quittent l'entrée `other`. Aucune clé n'est ajoutée, renommée ou supprimée sur l'objet ticket ni sur les lignes du rapport ; aucun autre code de statut ne change. `GET /api/tickets` (avec ses filtres `status` et `breached`), `GET /api/tickets/{id}`, `PATCH /api/tickets/{id}/status`, `PATCH /api/tickets/{id}/assignee`, les routes `/api/agents` et `GET /api/stats` ne sont pas modifiés.

### Ce qui change pour vous

Les tickets déjà enregistrés avec la priorité `urgent` sont conservés tels quels : leur échéance n'est pas recalculée et reste celle enregistrée à l'époque (24 heures après la création, ou `null` pour les plus anciens, qui ne sont donc jamais en dépassement). Ils sont toutefois désormais comptés dans la ligne `urgent` du rapport SLA, et non plus dans l'entrée `other`. La liste et la consultation des tickets les renvoient inchangés. Rien n'est à faire de votre côté.

---

Côté exploitation, TicketFlow peut désormais être lancé dans un conteneur Docker ; cela ne change rien à l'API ni à vos appels.

---

## Support et accompagnement

Pour toute question ou pour signaler un incident, vous pouvez :

- Contacter l'équipe projet
- Déposer une demande dans le dossier `requests/` du dépôt

Bien cordialement,
L'équipe IT
