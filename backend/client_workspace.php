<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['client_id']) || $_SESSION['user_type'] !== 'client') {
    header('Location: ../index.html');
    exit;
}

$client_id = (int)$_SESSION['client_id'];
$client_name = $_SESSION['client_name'] ?? 'Client';

function h($val) {
    return htmlspecialchars((string)$val, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="fr" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Espace Client | Créer un Projet</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="../lang_dict.js"></script>
    <style>
        :root {
            --bg-color: #f8fafc;
            --surface: #ffffff;
            --primary: #10b981;
            --primary-dark: #059669;
            --text-main: #0f172a;
            --text-muted: #64748b;
            --border: #e2e8f0;
        }
        * { box-sizing: border-box; font-family: 'Plus Jakarta Sans', sans-serif; margin: 0; padding: 0; }
        body { background-color: var(--bg-color); color: var(--text-main); }
        
        /* Premium Nav */
        .workspace-nav {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(16px); -webkit-backdrop-filter: blur(16px);
            border-bottom: 1px solid var(--border);
            padding: 1rem 2rem;
            display: flex; justify-content: space-between; align-items: center;
            position: sticky; top: 0; z-index: 100;
        }
        .nav-brand { font-size: 1.25rem; font-weight: 800; color: var(--primary-dark); text-decoration: none; display: flex; align-items: center; gap: 0.5rem; }
        .nav-actions { display: flex; gap: 1rem; align-items: center; }
        .btn-dash { padding: 0.5rem 1rem; font-weight: 600; background: var(--surface); border: 1px solid var(--border); border-radius: 999px; text-decoration: none; color: var(--text-main); font-size: 0.9rem; transition: 0.2s; }
        .btn-dash:hover { border-color: var(--primary); color: var(--primary); }
        .btn-logout { padding: 0.5rem 1.2rem; font-weight: 700; background: #fee2e2; color: #b91c1c; border-radius: 999px; border: none; cursor: pointer; transition: 0.2s;}
        
        /* Layout */
        .container { max-width: 900px; margin: 3rem auto; padding: 0 1rem; }
        
        .header-content { text-align: center; margin-bottom: 3rem; }
        .header-content h1 { font-size: 2.5rem; font-weight: 800; color: var(--text-main); margin-bottom: 0.5rem; letter-spacing: -0.5px; }
        .header-content p { color: var(--text-muted); font-size: 1.1rem; }
        
        /* Premium Form */
        .creation-form {
            background: var(--surface);
            padding: 2.5rem;
            border-radius: 24px;
            box-shadow: 0 10px 40px -10px rgba(0,0,0,0.05);
            border: 1px solid var(--border);
        }
        
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; }
        .full-width { grid-column: 1 / -1; }
        
        .form-group { display: flex; flex-direction: column; gap: 0.5rem; }
        .form-label { font-weight: 700; font-size: 0.95rem; color: var(--text-main); }
        .form-input {
            padding: 0.85rem 1.25rem;
            border: 2px solid var(--border);
            border-radius: 12px;
            font-size: 1rem;
            transition: all 0.2s;
            background: #f8fafc;
            outline: none;
        }
        .form-input:focus { border-color: var(--primary); background: #ffffff; box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.1); }
        select.form-input { appearance: none; background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e"); background-repeat: no-repeat; background-position: right 1rem center; background-size: 1em; }
        textarea.form-input { min-height: 120px; resize: vertical; }

        .btn-submit {
            grid-column: 1 / -1;
            padding: 1.25rem;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 1.1rem;
            font-weight: 800;
            cursor: pointer;
            transition: 0.3s;
            box-shadow: 0 10px 20px rgba(16, 185, 129, 0.25);
            margin-top: 1rem;
        }
        .btn-submit:hover { transform: translateY(-3px); box-shadow: 0 15px 25px rgba(16, 185, 129, 0.35); filter: brightness(1.05); }

        /* Status Alert */
        .alert { padding: 1rem 1.5rem; border-radius: 12px; font-weight: 600; margin-bottom: 2rem; display: none; }
        .alert-success { background: #ecfdf5; color: #065f46; border: 1px solid #10b981; }
        .alert-error { background: #fef2f2; color: #991b1b; border: 1px solid #ef4444; }

        /* ── Premium Language Pill Switcher ── */
        .lang-pill-switcher {
            display: inline-flex;
            background: #f1f5f9;
            border-radius: 999px;
            padding: 4px;
            gap: 2px;
            border: 1px solid var(--border);
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

    <nav class="workspace-nav">
        <a href="../index.html" class="nav-brand"><i class="fas fa-file-invoice"></i> <span data-i18n="cw_espace_client">ESPACE CLIENT</span></a>
        <div class="nav-actions">
            <span style="font-size: 0.9rem; font-weight: 600;"><span data-i18n="cw_welcome">Bienvenue, </span><?= h($client_name) ?></span>
            <a href="client_dashboard.php" class="btn-dash"><i class="fas fa-tachometer-alt"></i> <span data-i18n="cw_dashboard">Mon Tableau de bord</span></a>
            <button class="btn-logout" onclick="logout()"><i class="fas fa-sign-out-alt"></i> <span data-i18n="cw_logout">Déconnexion</span></button>
            <div class="lang-pill-switcher">
                <button class="lang-opt" id="btn-fr" onclick="setLang('fr')">🇫🇷 FR</button>
                <button class="lang-opt" id="btn-ar" onclick="setLang('ar')">🇲🇦 AR</button>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="header-content">
            <h1 data-i18n="cw_title">Publier un nouveau projet</h1>
            <p data-i18n="cw_desc">Remplissez les détails ci-dessous pour recevoir des offres des meilleurs artisans qualifiés.</p>
        </div>
        
        <div id="alertBox" class="alert"></div>

        <form id="projectForm" class="creation-form form-grid">
            <div class="form-group full-width">
                <label class="form-label" data-i18n="cw_proj_title">Titre du Projet</label>
                <input type="text" name="title" class="form-input" placeholder="Ex: Rénovation complète de la salle de bain" data-i18n-placeholder="cw_proj_title_ph" required>
            </div>
            
            <div class="form-group">
                <label class="form-label" data-i18n="cw_category">Catégorie</label>
                <select name="category" class="form-input" required>
                    <option value="" data-i18n="cw_cat_select">Sélectionner une catégorie</option>
                    <option value="نجارة" data-i18n="cw_cat_carpentry">Menuiserie</option>
                    <option value="سباكة" data-i18n="cw_cat_plumb">Plomberie</option>
                    <option value="كهرباء" data-i18n="cw_cat_elec">Électricité</option>
                    <option value="بناء" data-i18n="cw_cat_build">Construction</option>
                    <option value="صباغة" data-i18n="cw_cat_paint">Peinture</option>
                    <option value="أخرى" data-i18n="cw_cat_other">Autre...</option>
                </select>
            </div>
            
            <div class="form-group">
                <label class="form-label"><span data-i18n="cw_budget">Budget Estimé (MAD)</span> <small style="font-weight: normal; color:var(--text-muted);" data-i18n="cw_optional">(Optionnel)</small></label>
                <input type="number" name="budget" class="form-input" placeholder="Ex: 5000" data-i18n-placeholder="cw_budget_ph">
            </div>

            <div class="form-group full-width">
                <label class="form-label" data-i18n="cw_detail_desc">Description Détaillée</label>
                <textarea name="description" class="form-input" placeholder="Décrivez votre besoin en détail..." data-i18n-placeholder="cw_detail_desc_ph" required></textarea>
            </div>
            
            <div class="form-group">
                <label class="form-label" data-i18n="cw_city">Ville</label>
                <input type="text" name="city" class="form-input" placeholder="Ex: Casablanca" data-i18n-placeholder="cw_city_ph" required>
            </div>
            
            <div class="form-group">
                <label class="form-label"><span data-i18n="cw_neighborhood">Quartier</span> <small style="font-weight: normal; color:var(--text-muted);" data-i18n="cw_optional">(Optionnel)</small></label>
                <input type="text" name="neighborhood" class="form-input" placeholder="Ex: Maarif" data-i18n-placeholder="cw_neighborhood_ph">
            </div>
            
            <div class="form-group">
                <label class="form-label" data-i18n="cw_urgency">Urgence</label>
                <select name="urgency" class="form-input" required>
                    <option value="medium" data-i18n="cw_urg_normal">Normale</option>
                    <option value="high" data-i18n="cw_urg_high">Haute (Dès que possible)</option>
                    <option value="urgent" data-i18n="cw_urg_urgent">Urgente (Immédiat)</option>
                    <option value="low" data-i18n="cw_urg_low">Faible (Planifié pour plus tard)</option>
                </select>
            </div>
            
            <div class="form-group">
                <label class="form-label"><span data-i18n="cw_date">Date souhaitée</span> <small style="font-weight: normal; color:var(--text-muted);" data-i18n="cw_optional">(Optionnel)</small></label>
                <input type="date" name="desired_date" class="form-input">
            </div>
            
            <button type="submit" class="btn-submit" id="submitBtn"><i class="fas fa-paper-plane"></i> <span data-i18n="cw_btn_pub">Publier le Projet</span></button>
        </form>
    </div>

    <script>
        document.getElementById('projectForm').addEventListener('submit', async(e) => {
            e.preventDefault();
            const btn = document.getElementById('submitBtn');
            const alertBox = document.getElementById('alertBox');
            
            const currentLang = localStorage.getItem('hirafi_lang') || 'fr';
            const _t = (key) => (window.translations && window.translations[currentLang] && window.translations[currentLang][key]) ? window.translations[currentLang][key] : key;

            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> ' + _t('cw_publishing');
            btn.disabled = true;
            alertBox.style.display = 'none';
            alertBox.className = 'alert';
            
            const fd = new FormData(e.target);
            const data = Object.fromEntries(fd.entries());
            
            try {
                const res = await fetch('job_requests.php?action=create', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify(data)
                });
                const json = await res.json();
                
                if(json.success) {
                    alertBox.textContent = _t('cw_pub_success');
                    alertBox.classList.add('alert-success');
                    alertBox.style.display = 'block';
                    setTimeout(() => window.location.href = 'client_dashboard.php#projects', 1500);
                } else {
                    alertBox.textContent = json.message || _t('cw_pub_err');
                    alertBox.classList.add('alert-error');
                    alertBox.style.display = 'block';
                    btn.innerHTML = '<i class="fas fa-paper-plane"></i> ' + _t('cw_btn_pub');
                    btn.disabled = false;
                }
            } catch(err) {
                alertBox.textContent = _t('cw_srv_err');
                alertBox.classList.add('alert-error');
                alertBox.style.display = 'block';
                btn.innerHTML = '<i class="fas fa-paper-plane"></i> ' + _t('cw_btn_pub');
                btn.disabled = false;
            }
        });
        
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
            // Update button if not loading
            const btn = document.getElementById('submitBtn');
            if(btn && !btn.disabled) {
                btn.innerHTML = '<i class="fas fa-paper-plane"></i> ' + (dict['cw_btn_pub'] || 'Publier le Projet');
            }
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
