<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['craftsman_id']) || $_SESSION['user_type'] !== 'craftsman') {
    header('Location: ../index.html');
    exit;
}

$craftsman_id = (int)$_SESSION['craftsman_id'];
$craftsman_name = $_SESSION['craftsman_name'] ?? 'Artisan';

// Get craftsman specialization for smart filtering
$stmt = $conn->prepare("SELECT specialization, city FROM craftsmen WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $craftsman_id);
$stmt->execute();
$artisan = $stmt->get_result()->fetch_assoc();
$my_spec = $artisan['specialization'] ?? '';
$my_city = $artisan['city'] ?? '';

// Fetch available projects
// We consider status = 'open' or 'published' or default empty
$sql = "SELECT j.*, 
               c.full_name as client_name, 
               (SELECT COUNT(*) FROM proposals p WHERE p.request_id = j.id) as proposals_count
        FROM job_requests j 
        JOIN clients c ON j.client_id = c.id
        WHERE j.status = 'open' OR j.status IS NULL OR j.status = 'published'
        ORDER BY 
            CASE WHEN j.category = ? THEN 1 ELSE 2 END, /* Prioritize my spec */
            j.created_at DESC";
            
$stmt2 = $conn->prepare($sql);
$stmt2->bind_param("s", $my_spec);
$stmt2->execute();
$projects = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);

function h($val) {
    return htmlspecialchars((string)$val, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="fr" dir="ltr"> <!-- Usually premium apps default to multi, but we use FR/AR depending on lang_dict, we'll keep ltr generic premium -->
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Marketplace Artisan | HIRAFI</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="../lang_dict.js"></script>
    <style>
        :root {
            --bg-color: #f8fafc;
            --surface-color: #ffffff;
            --primary: #3b82f6;
            --primary-dark: #2563eb;
            --text-main: #0f172a;
            --text-muted: #64748b;
            --border-color: #e2e8f0;
            --danger: #ef4444;
            --success: #10b981;
            --warning: #f59e0b;
        }
        * { box-sizing: border-box; font-family: 'Plus Jakarta Sans', sans-serif; margin: 0; padding: 0; }
        body { background-color: var(--bg-color); color: var(--text-main); line-height: 1.6; }
        
        /* Premium Nav */
        .marketplace-nav {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border-bottom: 1px solid var(--border-color);
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 100;
        }
        .nav-brand { font-size: 1.25rem; font-weight: 800; color: var(--primary); text-decoration: none; display: flex; align-items: center; gap: 0.5rem; }
        .nav-actions { display: flex; gap: 1rem; align-items: center; }
        .btn-dash { padding: 0.5rem 1rem; font-weight: 600; background: var(--surface-color); border: 1px solid var(--border-color); border-radius: 999px; text-decoration: none; color: var(--text-main); font-size: 0.9rem; transition: all 0.2s; }
        .btn-dash:hover { border-color: var(--primary); color: var(--primary); }
        .btn-logout { padding: 0.5rem 1.2rem; font-weight: 700; background: #fee2e2; color: #b91c1c; border-radius: 999px; text-decoration: none; font-size: 0.9rem; border: none; cursor: pointer; transition: 0.2s;}
        .btn-logout:hover { background: #fca5a5; }

        /* Container */
        .container { max-width: 1200px; margin: 2rem auto; padding: 0 1rem; }
        
        /* Header */
        .workspace-header { margin-bottom: 2rem; }
        .workspace-title { font-size: 2.2rem; font-weight: 800; margin-bottom: 0.5rem; }
        .workspace-subtitle { color: var(--text-muted); font-size: 1.1rem; }
        
        /* Filters */
        .smart-filters {
            background: var(--surface-color);
            padding: 1.5rem;
            border-radius: 20px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02), 0 2px 4px -1px rgba(0, 0, 0, 0.02);
            border: 1px solid var(--border-color);
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            align-items: center;
        }
        .filter-input {
            flex: 1; min-width: 200px;
            padding: 0.75rem 1rem;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            font-size: 0.95rem;
            color: var(--text-main);
            background: #f8fafc;
            outline: none;
            transition: 0.2s;
        }
        .filter-input:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1); }
        
        /* Grid */
        .projects-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 1.5rem;
        }
        
        /* Premium Card */
        .project-card {
            background: var(--surface-color);
            border-radius: 20px;
            border: 1px solid var(--border-color);
            padding: 1.5rem;
            transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
            position: relative;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        .project-card::before {
            content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px;
            background: linear-gradient(90deg, var(--bg-color), var(--border-color));
            transition: 0.3s;
        }
        .project-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px -10px rgba(0, 0, 0, 0.08);
            border-color: rgba(59, 130, 246, 0.3);
        }
        .project-card:hover::before { background: linear-gradient(90deg, #3b82f6, #8b5cf6); }
        
        .card-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1rem; }
        .card-cat { display: inline-block; padding: 0.25rem 0.75rem; background: #eff6ff; color: #1d4ed8; border-radius: 999px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; }
        .card-cat.match { background: #ecfdf5; color: #047857; border: 1px solid #a7f3d0; } /* Highlight if matches artisan spec */
        .card-urgency { padding: 0.25rem 0.75rem; font-size: 0.75rem; font-weight: 700; border-radius: 999px; }
        
        .urgency-high { background: #fef2f2; color: #b91c1c; }
        .urgency-medium { background: #fffbeb; color: #b45309; }
        .urgency-low { background: #f8fafc; color: #64748b; }

        .card-title { font-size: 1.15rem; font-weight: 800; margin-bottom: 0.5rem; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
        .card-desc { font-size: 0.9rem; color: var(--text-muted); margin-bottom: 1.5rem; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; flex: 1; }
        
        .card-meta { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.5rem; padding-bottom: 1.5rem; border-bottom: 1px dashed var(--border-color); }
        .meta-item { display: flex; flex-direction: column; gap: 0.25rem; }
        .meta-label { font-size: 0.75rem; font-weight: 600; color: var(--text-muted); text-transform: uppercase; }
        .meta-value { font-size: 0.95rem; font-weight: 700; color: var(--text-main); display: flex; align-items: center; gap: 0.4rem; }
        .meta-value i { color: var(--primary); opacity: 0.8; }
        
        .card-actions { display: flex; gap: 0.75rem; }
        .btn-card { flex: 1; padding: 0.75rem; border-radius: 12px; font-weight: 700; cursor: pointer; text-align: center; text-decoration: none; transition: 0.2s; font-size: 0.9rem; }
        .btn-card-primary { background: linear-gradient(135deg, var(--primary), var(--primary-dark)); color: white; border: none; box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3); }
        .btn-card-primary:hover { box-shadow: 0 6px 16px rgba(59, 130, 246, 0.4); transform: translateY(-2px); }
        .btn-card-secondary { background: transparent; color: var(--text-main); border: 1px solid var(--border-color); }
        .btn-card-secondary:hover { background: #f1f5f9; border-color: #cbd5e1; }
        
        .empty-state { text-align: center; padding: 4rem 2rem; background: var(--surface-color); border-radius: 20px; border: 1px dashed var(--border-color); grid-column: 1/-1; }
        .empty-state i { font-size: 3rem; color: #cbd5e1; margin-bottom: 1rem; }
        .empty-state h3 { font-size: 1.25rem; margin-bottom: 0.5rem; }

        /* ── Premium Language Pill Switcher ── */
        .lang-pill-switcher {
            display: inline-flex;
            background: #f1f5f9;
            border-radius: 999px;
            padding: 4px;
            gap: 2px;
            border: 1px solid var(--border-color);
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        }
        .lang-opt {
            border: none;
            background: transparent;
            border-radius: 999px;
            padding: 5px 14px;
            font-size: 0.78rem;
            font-weight: 700;
            cursor: pointer;
            color: #64748b;
            transition: all 0.22s ease;
            letter-spacing: 0.4px;
            font-family: inherit;
        }
        .lang-opt:hover { color: var(--primary); }
        .lang-opt.active {
            background: #3b82f6;
            color: #fff;
            box-shadow: 0 2px 10px rgba(59,130,246,0.38);
        }
        
    </style>
</head>
<body>

    <nav class="marketplace-nav">
        <a href="../index.html" class="nav-brand"><i class="fas fa-hammer"></i> <span data-i18n="am_title">HIRAFI PRO</span></a>
        <div class="nav-actions">
            <span style="font-size: 0.9rem; font-weight: 600; opacity: 0.8;"><span data-i18n="am_welcome">Bonjour, </span><?= h($craftsman_name) ?></span>
            <a href="artisan_dashboard.php" class="btn-dash"><i class="fas fa-columns"></i> <span data-i18n="am_dashboard">Mon Tableau de bord</span></a>
            <button class="btn-logout" onclick="logout()"><i class="fas fa-sign-out-alt"></i> <span data-i18n="am_logout">Déconnexion</span></button>
            <div class="lang-pill-switcher">
                <button class="lang-opt" id="btn-fr" onclick="setLang('fr')">🇫🇷 FR</button>
                <button class="lang-opt" id="btn-ar" onclick="setLang('ar')">🇲🇦 AR</button>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="workspace-header">
            <h1 class="workspace-title" data-i18n="am_header_title">Marketplace des Projets</h1>
            <p class="workspace-subtitle" data-i18n="am_header_desc">Trouvez des opportunités qui correspondent à vos compétences à travers le Maroc.</p>
        </div>

        <div class="smart-filters">
            <div style="flex:100%; font-size: 0.85rem; font-weight:700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 1px;" data-i18n="am_smart_filters">Smart Filters</div>
            <input type="text" id="searchFilter" class="filter-input" placeholder="Rechercher (ex: électricité, Casablanca)..." data-i18n-placeholder="am_search_ph" oninput="filterProjects()">
            <select id="catFilter" class="filter-input" onchange="filterProjects()">
                <option value="" data-i18n="am_all_categories">Toutes les catégories</option>
                <option value="<?= h($my_spec) ?>"><span data-i18n="am_my_spec">★ Ma Spécialité</span> (<?= h($my_spec) ?>)</option>
                <option value="نجارة" data-i18n="cw_cat_carpentry">Menuiserie</option>
                <option value="سباكة" data-i18n="cw_cat_plumb">Plomberie</option>
                <option value="كهرباء" data-i18n="cw_cat_elec">Électricité</option>
                <option value="بناء" data-i18n="cw_cat_build">Construction</option>
                <option value="صباغة" data-i18n="cw_cat_paint">Peinture</option>
                <!-- Other categories -->
            </select>
            <select id="cityFilter" class="filter-input" onchange="filterProjects()">
                <option value="" data-i18n="am_all_cities">Toutes les villes</option>
                <option value="<?= h($my_city) ?>"><span data-i18n="am_my_city">📍 Ma Ville</span> (<?= h($my_city) ?>)</option>
                <option value="Casablanca">Casablanca</option>
                <option value="Rabat">Rabat</option>
                <option value="Marrakech">Marrakech</option>
                <option value="Tanger">Tanger</option>
                <option value="Fès">Fès</option>
            </select>
        </div>

        <div class="projects-grid" id="projectsGrid">
            <?php if(empty($projects)): ?>
                <div class="empty-state">
                    <i class="fas fa-search"></i>
                    <h3 data-i18n="am_empty_title">Aucun projet disponible pour le moment</h3>
                    <p style="color: var(--text-muted);" data-i18n="am_empty_desc">Revenez plus tard ou modifiez vos filtres.</p>
                </div>
            <?php else: ?>
                <?php foreach($projects as $p): 
                    $isMatch = ($p['category'] === $my_spec);
                    $urgencyClass = "urgency-low";
                    if ($p['urgency'] === 'high' || $p['urgency'] === 'urgent') $urgencyClass = "urgency-high";
                    if ($p['urgency'] === 'medium') $urgencyClass = "urgency-medium";
                    $urgencyI18n = "am_urg_" . strtolower($p['urgency']);
                ?>
                <div class="project-card" data-cat="<?= h(strtolower($p['category'])) ?>" data-city="<?= h(strtolower($p['city'])) ?>" data-search="<?= h(strtolower($p['title'].' '.$p['description'].' '.$p['neighborhood'])) ?>">
                    <div class="card-header">
                        <span class="card-cat <?php echo $isMatch ? 'match' : ''; ?>"><i class="fas <?= $isMatch ? 'fa-star' : 'fa-tag' ?>"></i> <?= h($p['category']) ?></span>
                        <span class="card-urgency <?= $urgencyClass ?>" data-i18n="<?= $urgencyI18n ?>"><?= h(ucfirst($p['urgency'])) ?></span>
                    </div>
                    <h3 class="card-title"><?= h($p['title']) ?></h3>
                    <p class="card-desc"><?= h($p['description']) ?></p>
                    
                    <div class="card-meta">
                        <div class="meta-item">
                            <span class="meta-label" data-i18n="am_budget_est">Budget Est.</span>
                            <span class="meta-value"><i class="fas fa-wallet"></i> <?php if($p['budget']): ?><?= h($p['budget']) ?> <span data-i18n="am_mad">MAD</span><?php else: ?><span data-i18n="am_to_discuss">À discuter</span><?php endif; ?></span>
                        </div>
                        <div class="meta-item">
                            <span class="meta-label" data-i18n="am_location">Localisation</span>
                            <span class="meta-value"><i class="fas fa-map-marker-alt"></i> <?= h($p['city']) ?></span>
                        </div>
                        <div class="meta-item">
                            <span class="meta-label" data-i18n="am_client_id">ID Client</span>
                            <span class="meta-value" style="font-size: 0.85rem;"><i class="fas fa-user-shield"></i> <span data-i18n="am_verified">Vérifié</span> #<?= h($p['client_id']) ?></span>
                        </div>
                        <div class="meta-item">
                            <span class="meta-label" data-i18n="am_proposals">Propositions</span>
                            <span class="meta-value"><i class="fas fa-file-signature"></i> <?= h($p['proposals_count']) ?> <span data-i18n="am_received">reçues</span></span>
                        </div>
                    </div>
                    
                    <div class="card-actions">
                        <a href="artisan_dashboard.php?section=job-requests" class="btn-card btn-card-secondary" data-i18n="am_btn_details">Détails</a>
                        <a href="artisan_dashboard.php?section=job-requests" class="btn-card btn-card-primary" data-i18n="am_btn_offer">Proposer Offre</a>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function filterProjects() {
            const search = document.getElementById('searchFilter').value.toLowerCase();
            const cat = document.getElementById('catFilter').value.toLowerCase();
            const city = document.getElementById('cityFilter').value.toLowerCase();
            
            const cards = document.querySelectorAll('.project-card');
            let visibleCount = 0;
            
            cards.forEach(card => {
                const cSearch = card.getAttribute('data-search');
                const cCat = card.getAttribute('data-cat');
                const cCity = card.getAttribute('data-city');
                
                let match = true;
                if (search && !cSearch.includes(search) && !cCat.includes(search) && !cCity.includes(search)) match = false;
                if (cat && cCat !== cat) match = false;
                if (city && cCity !== city) match = false;
                
                if (match) {
                    card.style.display = 'flex';
                    visibleCount++;
                } else {
                    card.style.display = 'none';
                }
            });
            
            // Check for empty state handling if all hidden (optional implementation)
        }
        
        async function logout() {
            await fetch('logout.php');
            window.location.href = '../index.html';
        }

        // ── Premium Lang Switcher ──
        function applyLocalTranslations(lang) {
            if (!window.translations || !window.translations[lang]) return;
            const dict = window.translations[lang];
            document.querySelectorAll('[data-i18n]').forEach(el => {
                const key = el.getAttribute('data-i18n');
                if (dict[key]) {
                    el.textContent = dict[key];
                }
            });
            document.querySelectorAll('[data-i18n-placeholder]').forEach(el => {
                const key = el.getAttribute('data-i18n-placeholder');
                if (dict[key]) {
                    el.placeholder = dict[key];
                }
            });
        }

        function setLang(lang) {
            localStorage.setItem('hirafi_lang', lang);
            document.documentElement.lang = lang;
            document.documentElement.dir = lang === 'ar' ? 'rtl' : 'ltr';
            document.querySelectorAll('.lang-opt').forEach(b => b.classList.remove('active'));
            const btn = document.getElementById('btn-' + lang);
            if (btn) btn.classList.add('active');
            
            applyLocalTranslations(lang);
        }
        document.addEventListener('DOMContentLoaded', function() {
            const saved = localStorage.getItem('hirafi_lang') || 'fr';
            setLang(saved);
        });
    </script>
</body>
</html>
