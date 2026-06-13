# Pictionary DevOps

> Déploiement continu d'une application multi-composants dans un environnement de type production, en appliquant les pratiques DevOps : conteneurisation, Infrastructure-as-Code, intégration et déploiement continus, zéro-downtime, observabilité et logging centralisé.

**Dépôt :** `salim-nejdi/pictionary-devops` · **Auteur :** Antoinse, Rudy, Salim

> Le périmètre évalué est l'**industrialisation DevOps**. L'application Pictionary (Application multi-composants : front PHP/Apache + API + base MariaDB) sert de support de démonstration.

---

## Sommaire

- [Vue d'ensemble](#vue-densemble)
- [Architecture](#architecture)
- [Couverture des exigences du projet](#couverture-des-exigences-du-projet)
- [Structure du dépôt](#structure-du-dépôt)
- [Choix techniques](#choix-techniques)
  - [1. Isolation par conteneurs](#1-isolation-par-conteneurs--docker)
  - [2. Infrastructure as Code](#2-infrastructure-as-code--ansible--terraform)
  - [3. Environnements (staging / production)](#3-environnements--staging--production)
  - [4. Intégration continue](#4-intégration-continue-ci)
  - [5. Déploiement continu](#5-déploiement-continu-cd)
  - [6. Zéro-downtime et haute disponibilité](#6-zéro-downtime-et-haute-disponibilité)
  - [7. Gestionnaire d'artefacts](#7-gestionnaire-dartefacts)
  - [8. Observabilité](#8-observabilité-et-golden-signals)
  - [9. Logging centralisé](#9-logging-centralisé)
  - [10. Gestion des secrets](#10-gestion-des-secrets)
- [Déploiement canary](#déploiement-canary--répartition-de-trafic)
- [Difficultés rencontrées et solutions](#difficultés-rencontrées-et-solutions)
- [Synthèse des choix](#synthèse-des-choix)

---

## Vue d'ensemble

La chaîne de livraison, entièrement versionnée dans ce dépôt, suit l'enchaînement :

```
Code (Git) → Image (Docker/GHCR) → Infrastructure (Terraform/OVH)
   → Configuration (Ansible) → Orchestration (K3s) → CI/CD (GitHub Actions)
   → Observabilité (Prometheus/Grafana/Loki) → Pilotage (bot Discord)
```

Un changement de code poussé sur `main` déclenche un pipeline unique : build de l'image, déploiement en staging, smoke tests, versionnage automatique, puis déploiement en production sous validation humaine.

---

## Architecture

```
                       push main
                          │
                          ▼
   ┌─────────┐   ┌──────────────┐   ┌─────────────┐   ┌─────────┐
   │  build  │──▶│deploy-staging│──▶│smoke-tests │──▶│ release │
   │ (image) │   │  (TF+Ansible)│   │  (HTTP 200) │   │(semver) │
   └─────────┘   └──────────────┘   └─────────────┘   └────┬────┘
                                                            │
                                              validation humaine
                                                            ▼
                                                    ┌──────────────┐
                                                    │ deploy-prod  │
                                                    │ image promue │
                                                    └──────┬───────┘
                                                           │
                          ┌────────────────────────────────┼───────────────┐
                          ▼                                 ▼               ▼
                  ┌───────────────┐                ┌──────────────┐  ┌──────────────┐
                  │  NŒUD STABLE  │  IngressRoute  │ NŒUD CANARY  │  │ Observabilité│
                  │ ns pictionary │◀── pondéré ───▶│  ns canary  │  │ Prom/Graf/   │
                  │  2 répliques  │   (Traefik)    │  2 répliques │  │ Loki + alert │
                  └───────┬───────┘                └──────┬───────┘  └──────────────┘
                          │                               │
                          └──────────► MariaDB ◀─────────┘
                                    (DB partagée)
```

La production fait coexister **deux nœuds applicatifs** : le nœud stable (`pictionary`) et le nœud canary (`pictionary-canary`), exposés via un routage Traefik pondéré permettant le déploiement progressif sans interruption.

---

## Couverture des exigences du projet

| Exigence de l'énoncé | Mise en œuvre |
|----------------------|---------------|
| **Isolation par conteneurs** | Image Docker multi-stage, publiée sur GHCR (registry) |
| **Infrastructure as Code** | Ansible pour Docker/K3s + stack applicative ; Terraform pour le provisionnement des VM |
| **Environnement de staging** | Préproduction isolée, même code, workspace HCP et environnement GitHub dédiés |
| **Intégration continue** | Pipeline GitHub Actions : build image + smoketest + publication d'artefact |
| **Déploiement continu** | Mise à jour automatique des composants via Ansible (staging) puis production sous validation |
| **Zéro-downtime** | `RollingUpdate` avec `maxUnavailable: 0` + déploiement canary pondéré |
| **Haute disponibilité** | Application web déployée en 2 répliques  (pod) |
| **Production multi-nœuds** | Deux nœuds applicatifs : stable + canary |
| **Observabilité (4 golden signals)** | Prometheus + Grafana : latence, trafic, erreurs, saturation |
| **Logging centralisé** | Loki + Promtail, recherche par service / date / namespace |
| **Configuration CI/CD versionnée (IaC)** | Tous les workflows en YAML versionnés dans `.github/workflows/` |

Fonctionnalités complémentaires : versionnage automatique (`semantic-release`), immuabilité d'artefact (promotion sans rebuild), déploiement canary à répartition de trafic pilotable (via bot discord), alerting multi-canal (courriel + Discord), et bot d'exploitation Discord.

---

## Structure du dépôt

```
.
├── src/                       # Application + Dockerfile (multi-stage)
│   ├── Dockerfile
│   ├── index.php
│   └── metrics.php            # Endpoint métriques Prometheus
├── terraform/                 # Provisionnement infrastructure (OVH/OpenStack)
│   ├── main.tf · variables.tf · outputs.tf · versions.tf
├── ansible/                   # Configuration et déploiement (IaC)
│   ├── playbook.yml
│   ├── group_vars/all/        # vars.yml + vault.yml (chiffré)
│   └── roles/
│       ├── k3s/               # Installation du cluster
│       ├── deploy/            # Namespace + DB + web stable
│       ├── canary/            # Nœud canary + routage pondéré (prod)
│       └── monitoring/        # Prometheus/Grafana/Loki/alerting (prod)
├── kubernetes/                # Manifestes K8s
│   ├── 00-namespace · 01-secret · 02-mariadb · 03-web · 04-ingress
│   └── canary/                # Namespace, web et routage pondéré canary
├── .github/workflows/         # Pipelines CI/CD (IaC)
│   ├── ci-cd.yml              # Pipeline principal
│   ├── deploy.yml #(n'a pas été mené jusqu'au bout) · destroy.yml
│   ├── rollback.yml · promote-canary.yml · canary-weight.yml
│   └── notify-pr.yml
└── docker-local/              # Environnement de test local de l'application(docker-compose)
```

---

## Choix techniques

### 1. Isolation par conteneurs — Docker

Chaque exécution applicative est isolée dans un conteneur. L'image est construite avec un **`Dockerfile` multi-stage** : une étape `builder` compile les extensions PHP (`pdo_mysql`), une étape `production` ne récupère que les artefacts compilés = image finale plus légère et surface d'attaque réduite.

**Décision structurante :** la version applicative n'est **pas gravée dans l'image**. L'image est neutre ; la version est injectée au démarrage par Kubernetes via `APP_VERSION`.  C'est ce qui permet de promouvoir une même image de staging en production sans la reconstruire (immuabilité d'artefact).

Les images sont publiées sur **GitHub Container Registry (GHCR)**, choisi pour son intégration native avec GitHub Actions : le GITHUB_TOKEN fourni automatiquement au pipeline authentifie le push sans secret supplémentaire à gérer.

### 2. Infrastructure as Code — Ansible + Terraform

Conformément à l'exigence IaC, l'ensemble des déploiements est reproductible sous forme de code versionné.

- **Ansible** configure la VM de façon **idempotente** et sans agent : installation de K3s, déploiement de la stack applicative, supervision. Organisation en **rôles** (`k3s`, `deploy`, `canary`, `monitoring`).
- **Terraform** provisionne les VM sur **OVH Horizon (OpenStack)**. State distant sur **HCP Terraform** (persistance CI/CD + verrouillage), sélection d'environnement par **tags de workspace** (`TF_WORKSPACE`), un même code servant staging et production.

La séparation des responsabilités est nette : Terraform crée l'infrastructure, Ansible la configure. On démarre d'une VM avec OS Linux déjà installé. L'objectif initial visait deux nœuds Kubernetes distincts pour la production ; le choix s'est finalement porté sur une production stable et canary cohabitant sur le même nœud, distinguées par namespace (pictionary et pictionary-canary). Il s'agit d'une séparation logique (organisation des ressources), retenue comme compromis adapté aux restrictions de ressources : elle permet de faire coexister deux versions et d'y router le trafic de façon pondérée.

### 3. Environnements — staging / production

| Environnement | Détails |
|---------------|---------|
| **Staging** | Préproduction isolée, même code que la production, déployée et testée automatiquement à chaque pipeline |
| **Production** | Cluster K3s, deux instances applicatifs (stable + canary), conteneurisé |

Les deux environnements sont séparés par **workspaces Terraform** (state isolé) et **environnements GitHub** (`production` protégé par reviewers obligatoires → validation humaine). Le projet suit un modèle **trunk-based** sur `main`.

### 4. Intégration continue (CI)

**Pipeline GitHub Actions** déclenché par `push` sur `main`, avec filtrage par chemins. Étapes liées par `needs` : `build → deploy-staging → smoke-tests → release → deploy-prod`.

- **Build & publication d'artefact** : image Docker construite depuis le commit, étiquetée par le SHA court, poussée sur GHCR.
- **Contrôle qualité** : smoke test vérifiant que le staging répond réellement (HTTP 200, présence du contenu, API fonctionnelle).
- **Versionnage automatique** : `semantic-release` (Conventional Commits) déduit la version des messages de commit. Abandon du tag `latest` au profit de versions sémantiques traçables.
- La configuration du pipeline est elle-même de l'**IaC** : tous les workflows sont en YAML versionnés.

### 5. Déploiement continu (CD)

Une fois l'image générée, le déploiement est automatisé via Ansible (staging) puis production sous validation.

**Immuabilité de l'artefact :** l'image testée en staging est **promue en production sans recompilation** (`docker pull` + `tag` + `push`). L'artefact déployé est exactement celui qui a été testé. La version lisible étant injectée au runtime, une même image peut porter plusieurs étiquettes sans contradiction.

Des workflows dédiés et contrôlé âr bot discord complètent le déploiement : destruction d'infrastructure, rollback, ~~déploiement à la demande~~, ajustement du trafic canary et promotion.

### 6. Zéro-downtime et haute disponibilité

La contrainte de continuité de service est traitée à plusieurs niveaux :

- **Mises à jour sans coupure** : le `Deployment` Kubernetes utilise `RollingUpdate` avec `maxUnavailable: 0` — un nouveau pod est prêt avant la suppression de l'ancien.
- **Déploiement progressif (canary)** : la nouvelle version est déployée sur le nœud canary et reçoit le trafic de façon **graduelle et pilotée**, jamais en bascule brutale.
- **Haute disponibilité** : l'application web tourne en **2 répliques** (possible car sans état).

### 7. Gestionnaire d'artefacts

Les images Docker sont stockées sur **GitHub Container Registry (GHCR)**, qui sert de registry pour l'ensemble de la chaîne. 

### 8. Observabilité et golden signals

Pile **kube-prometheus-stack (Prometheus + Grafana)**, déployée **uniquement en production**. Les sondes conteneurs/cluster sont fournies par **node-exporter** et **kube-state-metrics**.

Le monitoring couvre les **quatre golden signals SRE** :

| Signal | Mise en œuvre |
|--------|---------------|
| **Latence** | Temps de réponse de l'application (métriques HTTP) |
| **Trafic** | `pictionary_requests_total`, taux de requêtes |
| **Erreurs** | État de la base (`pictionary_db_up`), disponibilité des pods |
| **Saturation** | CPU / mémoire des pods vs limites définies |

Les **métriques applicatives** sont exposées par l'endpoint `/metrics.php` (format Prometheus), scrapé via un `ServiceMonitor`. Un **tableau de bord Grafana** dédié présente ces indicateurs.

**Alerting :** 4 règles Prometheus (DB injoignable, aucun pod disponible, mémoire/CPU élevés) routées par Alertmanager vers **courriel** (SMTP) et **Discord**.

### 9. Logging centralisé

**Loki + Promtail** centralisent les logs de tous les services (`pods → Promtail → Loki → Grafana`). Les logs sont consultables dans Grafana, avec recherche **par service, par namespace et par date** — utile pour corréler les composants en cas d'incident. Loki est retenu comme alternative légère à la stack ELK.

### 10. Gestion des secrets

- **Ansible Vault** chiffre les secrets de plateforme (DB, Grafana, SMTP), versionnés chiffrés dans le dépôt. Ce choix illustre une seconde approche de gestion des secrets, complémentaire de GitHub Secrets ; il reste remplaçable par ces derniers, mais a été retenu pour démontrer la maîtrise des deux mécanismes.
- **GitHub Secrets** stocke les identifiants du pipeline (OpenStack, Terraform, SSH, webhooks).
- Les **secrets Kubernetes** sont générés dynamiquement par Ansible à partir du Vault , avec no_log activé : aucune valeur sensible en clair n'est écrite sur le disque ni dans un manifeste versionné. Les valeurs réelles ne sont versionnées que chiffrées (Ansible Vault) ; le manifeste de secret du dépôt ne contient que des emplacements à substituer.

---

## Déploiement canary — répartition de trafic (Bonus)

Au-delà du rolling update, un véritable **déploiement canary pondéré** est mis en place via le CRD **`TraefikService` weighted** :

- Une seconde version (« canary ») tourne dans le namespace `pictionary-canary`, en parallèle du nœud stable.
- Le trafic est réparti selon des **poids configurables** entre les deux versions.
- À chaque déploiement, le poids du canary est remis à **0** : une nouvelle version ne reçoit aucun trafic sans action explicite.
- Un second `ServiceMonitor` supervise le canary séparément, permettant de comparer les deux versions.
- Le poids est ajustable à la demande via le bot discord (`/canary <poids>`), et le canary peut être **promu** en stable une fois validé (`/promote`).

C'est la réponse concrète à la contrainte zéro-downtime : montée en charge progressive et réversible d'une nouvelle version.


---

## Synthèse des choix

| Domaine | Choix retenu | Justification principale |
|---------|--------------|--------------------------|
| Conteneurisation | Docker multi-stage, image neutre | Image légère, immuabilité (version au runtime) |
| Registre d'images | GHCR | Intégration native GitHub Actions |
| Infrastructure | Terraform + OVH/OpenStack | Provisionnement déclaratif et versionné |
| State Terraform | HCP distant, sélection par tags | Persistance CI/CD, verrouillage, un seul code |
| Configuration | Ansible en rôles | Idempotence, sans agent, séparation des responsabilités |
| Orchestration | K3s + Traefik | Kubernetes léger, contrôleur d'entrée intégré |
| Modèle de branche | Trunk-based (`main` unique) | Simplicité, évite les écueils de la fusion automatique |
| Versionnage | semantic-release / Conventional Commits | Versions automatiques traçables, abandon de `latest` |
| Promotion | Image immuable promue sans rebuild | L'artefact en production est celui qui a été testé |
| Multi-environnement | Workspaces TF + environnements GitHub | Séparation par configuration, validation humaine en prod |
| Zéro-downtime | RollingUpdate + canary pondéré | Continuité de service, mise à jour progressive |
| Haute disponibilité | 2 répliques web | Tolérance à la perte d'un pod |
| Secrets | Ansible Vault + GitHub Secrets | Aucune valeur sensible en clair |
| Observabilité | Prometheus + Grafana (4 golden signals) | Monitoring métier et technique |
| Logging | Loki + Promtail | Centralisation légère, recherche par service/date |
| Déploiement progressif | Canary pondéré  | Mise en production maîtrisée et réversible |

---

*Tout est versionné — code, infrastructure, configuration et pipeline. Les déploiements sont automatisés et reproductibles, les artefacts immuables, la production supervisée et alertée, et les mises en production progressives, sans interruption de service et réversibles.*
