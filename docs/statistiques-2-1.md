# Statistiques 2.1 — provenance des visites et passages des robots

Cadrage du 27 novembre 2026. Demande de Marc issue du suivi d'Univers Guitare (note « Demandes au projet P3 », point 4), retenue parce qu'elle sert à tout site P3. Arbitrage du même jour : les statistiques restent réservées aux administrateurs, les rédacteurs n'y ont pas accès.

## Intention

Savoir d'où viennent les lecteurs et ce que lisent les machines, en particulier les assistants d'IA, sans script, sans cookie et sans interroger le navigateur. Seules les informations transmises par la requête HTTP sont utilisées.

## Comportement attendu

1. **Lecteurs.** Les compteurs de lecteurs (pages vues, visiteurs journaliers estimés, articles, pages d'entrée, provenance) et les compteurs utilisés par Accueil excluent tout robot reconnu, y compris les assistants d'IA qui lisent une page pour répondre à quelqu'un (Claude-User, ChatGPT-User, Perplexity-User…). En 2.0.4, ces derniers étaient comptés comme lecteurs.
2. **Passages des robots.** Un robot reconnu n'est plus simplement ignoré : son passage est compté à part, par jour, par page et par type :
   - moteur de recherche (Googlebot, Bingbot…) ;
   - collecte pour les IA (GPTBot, ClaudeBot, CCBot…) ;
   - IA consultant une page pour un utilisateur (ChatGPT-User, Claude-User, Perplexity-User, OAI-SearchBot…) ;
   - autre robot (outils de mesure, aperçus de réseaux sociaux, robots non classés).
   Seul le type est conservé, jamais le User-Agent complet.
3. **Provenance classée.** Le domaine référent déjà noté à la première visite du jour est aussi rangé en : moteur de recherche, assistant d'IA, autre site, lien interne, accès direct.
4. **utm_source.** Lorsque l'adresse d'entrée porte `utm_source`, sa valeur est notée (lettres, chiffres, point, tiret, soulignement ; 64 caractères au plus). Un assistant d'IA reconnu par ce paramètre (par exemple `chatgpt.com`) compte comme provenance « assistant d'IA » même sans référent.
5. **Provenance par page d'entrée.** Chaque première visite du jour relie la page d'entrée et sa provenance classée.
6. **Restitution.** Le tableau de bord ajoute : provenance par type, valeurs utm_source, provenance par page d'entrée, passages des robots par type et pages les plus lues par les IA pour un utilisateur. L'export CSV contient les totaux quotidiens puis le détail par jour, type de mesure, élément et total.
7. **Réglage.** « Compter les passages des robots » (activé par défaut) permet de l'arrêter sur un petit hébergement.

## Exclusions et règles maintenues

Aucune adresse IP ni User-Agent complet stockés ; empreinte journalière inchangée. Pages d'administration, sessions connectées, requêtes DNT, visites d'audit (`ug_audit=1`, `X-UG-Audit`) et réponses non admissibles restent exclues, robots compris. Pas de nouvelle table : les compteurs agrégés existants reçoivent de nouveaux types. Durée de conservation commune.

## Limites connues

Un robot qui se présente comme un navigateur reste compté comme lecteur. Une application qui n'envoie pas de référent tombe dans « accès direct ». Les visites issues des résumés IA de Google restent mêlées à la provenance Google. Les listes de robots et d'assistants sont à entretenir au fil des versions. Une page servie par un cache extérieur à P3 n'est pas comptée.

## Ressources

Une écriture SQLite par passage de robot compté. Le réglage du point 7 permet de l'arrêter. Mesure de charge sur mutualisé à rapprocher de la piste #61.

## Critères de validation

- Classement des User-Agent : un cas par type, plus des navigateurs courants restant « navigateur ».
- Un robot n'augmente ni pages vues, ni visiteurs, ni compteurs d'Accueil ; il augmente son compteur de type et de page.
- Réglage désactivé : aucun compteur de robot.
- Provenance classée, utm_source filtré, provenance par page d'entrée, export CSV.
- Accès refusé aux rédacteurs et visiteurs (contrôle existant `AdminAccess=admin`).
- Données des versions antérieures lisibles ; aucune migration requise.
- Tests du module, `validate_release.php`, distribution.

## Livraison

Module Statistiques 2.1.0, distribué indépendamment du moteur. Retour arrière : réinstaller 2.0.4 ; les nouveaux compteurs sont ignorés par l'ancienne version et expirent avec la durée de conservation.
