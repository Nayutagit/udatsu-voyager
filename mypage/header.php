<?php
// mypage/header.php
// Requires variables: $userName, $userPlan, $imagePath (optional)
?>
<header class="header">
  <div class="container header-inner" style="display: flex; justify-content: space-between; align-items: center;">
    <a href="mypage.php" class="logo">
      <img src="../img/udatsu-logo.png" alt="Udatsu Logo">
      <span>Udatsu</span>
    </a>
    
    <button class="hamburger" id="menuToggle" aria-label="Toggle menu">
      <span></span>
      <span></span>
      <span></span>
    </button>
  </div>
</header>

<div class="sidebar-overlay" id="sidebarOverlay"></div>
<nav class="sidebar" id="sidebar">
  <div style="margin-bottom: 20px; text-align: center;">
    <div class="user-badge" style="display: inline-block; margin-bottom: 10px;">
      <i class="fas fa-user-circle"></i> <?= htmlspecialchars($userName ?? 'ゲスト') ?>
    </div>
    <div class="user-badge" style="display: inline-block;">
      Plan: <strong><?= htmlspecialchars($userPlan ?? 'guest') ?></strong>
    </div>
  </div>
  
  <a href="mypage.php" class="sidebar-link"><i class="fas fa-home"></i> マイページ</a>
  <a href="timeline.php" class="sidebar-link"><i class="fas fa-hourglass-half"></i> タイムライン</a>
  <a href="my_udastack.php" class="sidebar-link"><i class="fas fa-layer-group"></i> My Udastack</a>
  <a href="network.php" class="sidebar-link"><i class="fas fa-globe"></i> ネットワーク</a>
  <a href="edit_profile.php" class="sidebar-link"><i class="fas fa-pen"></i> プロフィール編集</a>
  <a href="membership.php" class="sidebar-link"><i class="fas fa-crown"></i> プラン変更</a>
  
  <div style="margin-top: auto; padding-top: 20px; border-top: 1px solid rgba(255,255,255,0.1);">
    <a href="javascript:void(0)" class="sidebar-link" id="themeToggle">
      <i class="fas fa-adjust"></i> 表示モード切替
    </a>
  </div>
</nav>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const toggle = document.getElementById('menuToggle');
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    const themeToggle = document.getElementById('themeToggle');

    function toggleMenu() {
        sidebar.classList.toggle('active');
        overlay.classList.toggle('active');
    }

    toggle.addEventListener('click', toggleMenu);
    overlay.addEventListener('click', toggleMenu);

    // Theme Toggle
    const currentTheme = localStorage.getItem('udatsu_theme') || 'dark';
    if (currentTheme === 'light') {
        document.body.classList.add('light-mode');
    }

    themeToggle.addEventListener('click', () => {
        document.body.classList.toggle('light-mode');
        if (document.body.classList.contains('light-mode')) {
            localStorage.setItem('udatsu_theme', 'light');
        } else {
            localStorage.setItem('udatsu_theme', 'dark');
        }
    });
});
</script>
