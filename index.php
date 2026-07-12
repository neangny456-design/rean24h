<?php
require_once __DIR__ . '/database.php';
?>
<!doctype html>
<html lang="km">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Maths24h — ផ្ទាំងគ្រប់គ្រង</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link
      href="https://fonts.googleapis.com/css2?family=Moul&family=Kantumruy+Pro:wght@400;500;600;700&family=JetBrains+Mono:wght@500;700&display=swap"
      rel="stylesheet"
    />
    <link
      href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
      rel="stylesheet"
    />
    <style>
      :root {
        --paper: #fbf7ef;
        --paper-line: #e4dcc8;
        --cream-card: #fffdf8;
        --ink: #1d2b53;
        --ink-soft: #4c5680;
        --gold: #c89b3c;
        --gold-soft: #f0dfb0;
        --seal: #b23a2f;
        --jade: #3e7c59;
        --radius-tab: 4px 14px 14px 14px;
      }

      * {
        box-sizing: border-box;
      }

      body {
        font-family: "Kantumruy Pro", sans-serif;
        color: var(--ink);
        background-color: var(--paper);
        background-image:
          linear-gradient(var(--paper-line) 1px, transparent 1px),
          linear-gradient(90deg, var(--paper-line) 1px, transparent 1px);
        background-size: 28px 28px;
        min-height: 100vh;
      }

      .display,
      .brand-word,
      .section-title,
      .folder-title {
        font-family: "Moul", serif;
      }
      .mono {
        font-family: "JetBrains Mono", monospace;
      }

      a {
        text-decoration: none;
        color: inherit;
      }

      /* ===== Navbar ===== */
      .topbar {
        background: var(--cream-card);
        border-bottom: 2px solid var(--ink);
        position: sticky;
        top: 0;
        z-index: 40;
      }
      .brand-mark {
        width: 44px;
        height: 44px;
        border-radius: 10px;
        background: var(--ink);
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--gold-soft);
        font-family: "JetBrains Mono", monospace;
        font-weight: 700;
        font-size: 0.78rem;
        line-height: 1;
        text-align: center;
        flex-shrink: 0;
        box-shadow: 2px 2px 0 var(--gold);
      }
      .brand-word {
        font-size: 1.15rem;
        letter-spacing: 0.5px;
        color: var(--ink);
      }
      .brand-sub {
        font-size: 0.68rem;
        color: var(--ink-soft);
        letter-spacing: 0.4px;
      }

      .search-wrap {
        max-width: 420px;
      }
      .search-wrap input {
        background: var(--paper);
        border: 1.5px solid var(--paper-line);
        border-radius: 999px;
        padding: 0.5rem 1rem 0.5rem 2.4rem;
        font-size: 0.9rem;
      }
      .search-wrap input:focus {
        border-color: var(--ink);
        box-shadow: 0 0 0 3px rgba(29, 43, 83, 0.12);
        background: #fff;
      }
      .search-icon {
        position: absolute;
        left: 0.9rem;
        top: 50%;
        transform: translateY(-50%);
        color: var(--ink-soft);
      }

      .bell-btn {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: var(--paper);
        border: 1.5px solid var(--paper-line);
        display: flex;
        align-items: center;
        justify-content: center;
        position: relative;
      }
      .bell-dot {
        position: absolute;
        top: 6px;
        right: 7px;
        width: 9px;
        height: 9px;
        border-radius: 50%;
        background: var(--seal);
        border: 2px solid var(--cream-card);
      }
      .btn-ink {
        background: var(--ink);
        color: var(--paper);
        border: 1.5px solid var(--ink);
        font-weight: 600;
      }
      .btn-ink:hover {
        background: #141f3d;
        color: var(--gold-soft);
      }
      .btn-outline-ink {
        background: transparent;
        color: var(--ink);
        border: 1.5px solid var(--ink);
        font-weight: 600;
      }
      .btn-outline-ink:hover {
        background: var(--ink);
        color: var(--paper);
      }

      /* ===== Notice ticker ===== */
      .ticker {
        background: var(--gold-soft);
        border-bottom: 1px solid var(--paper-line);
        overflow: hidden;
        white-space: nowrap;
        padding: 0.4rem 0;
      }
      .ticker-track {
        display: inline-block;
        padding-left: 100%;
        animation: scrollLeft 22s linear infinite;
        font-size: 0.83rem;
        font-weight: 600;
        color: #6b4e17;
      }
      .ticker-track span {
        margin-right: 3.5rem;
      }
      @keyframes scrollLeft {
        from {
          transform: translateX(0);
        }
        to {
          transform: translateX(-100%);
        }
      }
      @media (prefers-reduced-motion: reduce) {
        .ticker-track {
          animation: none;
          padding-left: 1rem;
        }
      }

      /* ===== Layout ===== */
      .shell {
        display: flex;
        min-height: calc(100vh - 88px);
      }

      .sidebar {
        width: 236px;
        flex-shrink: 0;
        background: var(--cream-card);
        border-right: 2px solid var(--ink);
        padding: 1.4rem 1rem;
        transition:
          width 0.22s ease,
          padding 0.22s ease;
      }
      .sidebar.collapsed {
        width: 76px;
        padding: 1.4rem 0.6rem;
      }
      .sidebar.collapsed .side-label,
      .sidebar.collapsed .side-caption {
        display: none;
      }
      .side-caption {
        font-size: 0.72rem;
        text-transform: uppercase;
        letter-spacing: 1px;
        color: var(--ink-soft);
        margin: 0 0.6rem 1rem;
      }
      .side-link {
        display: flex;
        align-items: center;
        gap: 0.7rem;
        padding: 0.62rem 0.7rem;
        border-radius: 8px;
        color: var(--ink-soft);
        font-weight: 600;
        font-size: 0.92rem;
        margin-bottom: 0.2rem;
      }
      .side-link .ic {
        font-size: 1.05rem;
        width: 22px;
        text-align: center;
        flex-shrink: 0;
      }
      .side-link:hover {
        background: var(--paper);
        color: var(--ink);
      }
      .side-link.active {
        background: var(--ink);
        color: var(--gold-soft);
      }
      .side-toggle {
        border: 1.5px solid var(--paper-line);
        background: var(--paper);
        border-radius: 8px;
        width: 100%;
        padding: 0.4rem;
        margin-bottom: 1rem;
        color: var(--ink-soft);
        font-size: 0.85rem;
      }

      .main {
        flex: 1;
        padding: 2rem clamp(1rem, 3vw, 2.6rem);
        min-width: 0;
      }

      .greet-strip {
        background: var(--ink);
        color: var(--paper);
        border-radius: 14px;
        padding: 1.3rem 1.6rem;
        display: flex;
        flex-wrap: wrap;
        gap: 1rem;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 2rem;
        box-shadow: 4px 4px 0 var(--gold);
      }
      .greet-strip h1 {
        font-size: 1.05rem;
        margin: 0 0 0.2rem;
        font-weight: 700;
      }
      .greet-strip p {
        margin: 0;
        font-size: 0.82rem;
        color: #c9cfea;
      }
      .stat-chip {
        background: rgba(255, 255, 255, 0.08);
        border: 1px solid rgba(255, 255, 255, 0.18);
        border-radius: 10px;
        padding: 0.5rem 0.9rem;
        text-align: center;
        min-width: 84px;
      }
      .stat-chip .num {
        font-family: "JetBrains Mono", monospace;
        font-weight: 700;
        font-size: 1.05rem;
        color: var(--gold-soft);
      }
      .stat-chip .lbl {
        font-size: 0.66rem;
        color: #c9cfea;
      }

      .section-title {
        font-size: 1.05rem;
        color: var(--ink);
        margin-bottom: 1.1rem;
      }
      .section-title .em {
        font-family: "Kantumruy Pro", sans-serif;
      }

      /* ===== Subject folder cards ===== */
      .folder-row {
        --gap: 1.5rem;
      }
      .subject-folder {
        position: relative;
        background: var(--cream-card);
        border: 1px solid var(--paper-line);
        border-radius: var(--radius-tab);
        padding: 2.1rem 1.4rem 1.5rem;
        height: 100%;
        box-shadow:
          0 2px 0 rgba(29, 43, 83, 0.06),
          0 14px 26px -16px rgba(29, 43, 83, 0.35);
        transition:
          transform 0.22s ease,
          box-shadow 0.22s ease;
      }
      .subject-folder::before {
        content: "";
        position: absolute;
        top: -13px;
        left: 22px;
        width: 92px;
        height: 15px;
        background: var(--cream-card);
        border: 1px solid var(--paper-line);
        border-bottom: none;
        border-radius: 6px 6px 0 0;
      }
      .subject-folder::after {
        content: "";
        position: absolute;
        top: 0;
        right: 0;
        width: 0;
        height: 0;
        border-style: solid;
        border-width: 0 20px 20px 0;
        border-color: transparent var(--paper) transparent transparent;
        filter: drop-shadow(-1px 1px 1px rgba(29, 43, 83, 0.15));
        border-top-right-radius: 14px;
      }
      .subject-folder:hover {
        transform: translateY(-5px);
        box-shadow:
          0 4px 0 rgba(29, 43, 83, 0.08),
          0 22px 34px -16px rgba(29, 43, 83, 0.4);
      }

      .folder-icon {
        font-size: 1.9rem;
        line-height: 1;
      }
      .folder-title {
        font-size: 0.98rem;
        margin: 0.7rem 0 0.3rem;
        color: var(--ink);
      }
      .folder-desc {
        font-size: 0.82rem;
        color: var(--ink-soft);
        margin-bottom: 1.6rem;
        line-height: 1.55;
      }

      .stamp {
        position: absolute;
        bottom: 14px;
        right: 16px;
        width: 58px;
        height: 58px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        text-align: center;
        border: 2px dashed currentColor;
        transform: rotate(-9deg);
        font-family: "JetBrains Mono", monospace;
        font-weight: 700;
        font-size: 0.62rem;
        letter-spacing: 0.3px;
        padding: 0.3rem;
      }
      .stamp.stamp-gold {
        color: var(--gold);
      }
      .stamp.stamp-seal {
        color: var(--seal);
      }
      .stamp.stamp-jade {
        color: var(--jade);
      }

      .folder-link {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        font-weight: 700;
        font-size: 0.82rem;
        color: var(--ink);
        border-bottom: 1.5px solid var(--gold);
        padding-bottom: 2px;
      }
      .folder-link:hover {
        color: var(--seal);
        border-color: var(--seal);
      }

      /* ===== Footer ===== */
      footer {
        border-top: 2px solid var(--ink);
        background: var(--cream-card);
        padding: 1.1rem 1rem;
        text-align: center;
        font-size: 0.8rem;
        color: var(--ink-soft);
      }
      footer b {
        color: var(--ink);
      }

      @media (max-width: 820px) {
        .sidebar {
          position: fixed;
          top: 0;
          left: 0;
          height: 100vh;
          z-index: 60;
          transform: translateX(-100%);
        }
        .sidebar.mobile-open {
          transform: translateX(0);
        }
        .shell {
          position: relative;
        }
      }
    </style>
  </head>
  <body>
    <!-- Navbar -->
    <div class="topbar">
      <div
        class="container-fluid px-3 px-md-4 py-2 d-flex align-items-center gap-3"
      >
        <button
          class="btn btn-sm btn-outline-ink d-md-none"
          id="mobileToggle"
          aria-label="បើក/បិទម៉ឺនុយ"
        >
          ☰
        </button>
        <a
          href="index.php"
          class="d-flex align-items-center gap-2 text-decoration-none"
        >
          <span class="brand-mark">24h</span>
          <span class="d-none d-sm-block">
            <span class="brand-word d-block">Maths24h</span>
            <span class="brand-sub d-block">រៀនគណិតវិទ្យា២៤ម៉ោង</span>
          </span>
        </a>

        <div
          class="search-wrap position-relative mx-auto flex-grow-1 d-none d-md-block"
        >
          <span class="search-icon">🔍</span>
          <input
            type="text"
            class="form-control"
            placeholder="ស្វែងរកលំហាត់ ឬវិញ្ញាសា..."
          />
        </div>

        <div class="ms-auto d-flex align-items-center gap-2 flex-shrink-0">
          <div class="dropdown">
            <button
              class="bell-btn"
              data-bs-toggle="dropdown"
              aria-label="ដំណឹង"
            >
              🔔<span class="bell-dot"></span>
            </button>
            <ul
              class="dropdown-menu dropdown-menu-end p-2"
              style="width: 280px; font-size: 0.85rem"
            >
              <li class="px-2 pb-2 fw-bold" style="color: var(--ink)">
                ដំណឹងថ្មីៗ
              </li>
              <li>
                <a class="dropdown-item rounded py-2" href="#"
                  >📌 វិញ្ញាសាឌីប្លូម/បាក់ឌុបថ្មីត្រូវបានដាក់បញ្ចូល</a
                >
              </li>
              <li>
                <a class="dropdown-item rounded py-2" href="#"
                  >🏅 អ្នកទទួលបានប័ណ្ណសរសើរប្រចាំសប្ដាហ៍</a
                >
              </li>
            </ul>
          </div>
          <?php if (!empty($_SESSION['user_id'])): ?>
            <span class="d-none d-md-inline-block text-ink me-2 fw-semibold" style="font-size: 0.9rem;">
              👤 <?= htmlspecialchars($_SESSION['fullname'], ENT_QUOTES, 'UTF-8') ?>
            </span>
            <a href="logout.php" class="btn btn-sm btn-ink px-3">ចាកចេញ</a>
          <?php else: ?>
            <a href="register.php" class="btn btn-sm btn-ink px-3">បង្កើតគណនី</a>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Ticker -->
    <div class="ticker">
      <div class="ticker-track">
        <span>📌 វិញ្ញាសាឌីប្លូម ២០២៦ ត្រូវបានដាក់បញ្ចូលរួចរាល់</span>
        <span>📌 វិញ្ញាសាបាក់ឌុប ២០២៦ ត្រូវបានដាក់បញ្ចូលរួចរាល់</span>
        <span>🏅 សិស្សលេខ១ប្រចាំសប្ដាហ៍នេះ៖ សុភា ចាន់ដារ៉ា</span>
      </div>
    </div>

    <div class="shell">
      <!-- Sidebar -->
      <aside class="sidebar" id="sidebar">
        <button class="side-toggle" id="sideCollapse">‹‹ បង្រួម</button>
        <p class="side-caption">ម៉ឺនុយ</p>
        <a href="index.php" class="side-link active"
          ><span class="ic">🏠</span><span class="side-label">ផ្ទាំងគ្រប់គ្រង</span></a
        >
        <a href="category.php?slug=grade-9" class="side-link"
          ><span class="ic">📖</span><span class="side-label">គណិតវិទ្យាទី៩ (ឌីប្លូម)</span></a
        >
        <a href="category.php?slug=grade-12" class="side-link"
          ><span class="ic">📝</span><span class="side-label">គណិតវិទ្យាទី១២ (បាក់ឌុប)</span></a
        >
        <a href="#" class="side-link"
          ><span class="ic">🏅</span><span class="side-label">ប័ណ្ណសរសើរ</span></a
        >
        <a href="#" class="side-link"
          ><span class="ic">📅</span><span class="side-label">ប្រវត្តិប្រឡង</span></a
        >
        <a href="#" class="side-link"
          ><span class="ic">⚙️</span><span class="side-label">ការកំណត់</span></a
        >
      </aside>

      <!-- Main -->
      <main class="main">
        <div class="greet-strip">
          <div>
            <h1>សួស្តី 👋 ត្រៀមខ្លួនរួចរាល់ហើយឬនៅ?</h1>
            <p>បន្តការសិក្សារបស់អ្នក ដើម្បីឈានទៅដល់គោលដៅប្រឡងជាតិ</p>
          </div>
          <div class="d-flex gap-2">
            <div class="stat-chip">
              <div class="num mono">12</div>
              <div class="lbl">ថ្ងៃជាប់គ្នា</div>
            </div>
            <div class="stat-chip">
              <div class="num mono">860</div>
              <div class="lbl">XP សរុប</div>
            </div>
            <div class="stat-chip">
              <div class="num mono">7</div>
              <div class="lbl">ប័ណ្ណសរសើរ</div>
            </div>
          </div>
        </div>

        <h2 class="section-title">
          <span class="em">📊 📚</span> ជ្រើសរើសកម្រិតសិក្សា
        </h2>

        <div class="row folder-row g-4 mb-4">
          <div class="col-12 col-md-6 col-lg-4">
            <div class="subject-folder">
              <div class="folder-icon">📂</div>
              <h3 class="folder-title">គណិតវិទ្យា ថ្នាក់ទី៩ (ឌីប្លូម)</h3>
              <p class="folder-desc">
                លំហាត់ត្រៀមប្រឡងជាតិ ចែកតាមមេរៀន
                ព្រមទាំងលំហាត់អនុវត្តន៍ប្រចាំសប្ដាហ៍។
              </p>
              <a href="category.php?slug=grade-9" class="folder-link">ចូលមើលមេរៀន →</a>
              <div class="stamp stamp-gold">ថ្នាក់<br />ទី៩</div>
            </div>
          </div>

          <div class="col-12 col-md-6 col-lg-4">
            <div class="subject-folder">
              <div class="folder-icon">📁</div>
              <h3 class="folder-title">គណិតវិទ្យា ថ្នាក់ទី១២ (បាក់ឌុប)</h3>
              <p class="folder-desc">
                វិញ្ញាសាបាក់ឌុប ពេញលេញតាមកម្មវិធីអប់រំ
                ជាមួយពេលវេលាកំណត់ដូចប្រឡងពិត។
              </p>
              <a href="category.php?slug=grade-12" class="folder-link">ចូលធ្វើតេស្ត →</a>
              <div class="stamp stamp-seal">ថ្នាក់<br />ទី១២</div>
            </div>
          </div>

          <div class="col-12 col-md-6 col-lg-4">
            <div class="subject-folder">
              <div class="folder-icon">🗂️</div>
              <h3 class="folder-title">បណ្តុំវិញ្ញាសាចាស់ៗ</h3>
              <p class="folder-desc">
                វិញ្ញាសាប្រឡងឌីប្លូម និងបាក់ឌុប ពីឆ្នាំមុនៗ
                រួមទាំងចម្លើយពន្យល់លម្អិត។
              </p>
              <a href="category.php?slug=old-papers" class="folder-link">មើលឯកសារ →</a>
              <div class="stamp stamp-jade">ប័ណ្ណ<br />ចាស់</div>
            </div>
          </div>
        </div>
      </main>
    </div>

    <footer>© 2026 រក្សាសិទ្ធិដោយ <b>Maths24h</b> · រៀបរៀងដោយ៖ នាង នី</footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
      const sidebar = document.getElementById("sidebar");
      const collapseBtn = document.getElementById("sideCollapse");
      const mobileToggle = document.getElementById("mobileToggle");

      const savedState = localStorage.getItem("maths24h-sidebar");
      if (savedState === "collapsed") {
        sidebar.classList.add("collapsed");
        collapseBtn.textContent = "››";
      }

      collapseBtn.addEventListener("click", () => {
        sidebar.classList.toggle("collapsed");
        const collapsed = sidebar.classList.contains("collapsed");
        collapseBtn.textContent = collapsed ? "››" : "‹‹ បង្រួម";
        localStorage.setItem(
          "maths24h-sidebar",
          collapsed ? "collapsed" : "open",
        );
      });

      mobileToggle.addEventListener("click", () => {
        sidebar.classList.toggle("mobile-open");
      });
    </script>
  </body>
</html>
