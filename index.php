<?php
/**
 * JodRod (จอดรถ) - Flood Parking Community
 * Index / Home Page
 */
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>JodRod (จอดรถ) - Flood Parking Community | ชุมชนแบ่งปันที่จอดรถพ้นน้ำท่วม</title>
  <meta name="description" content="ระบบช่วยเหลือที่จอดรถปลอดภัยช่วงอุทกภัย หาที่จอดรถหนีน้ำท่วม และแบ่งปันพื้นที่จอดรถส่วนบุคคล">
  <link rel="stylesheet" href="assets/css/style.css">
  <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🚗</text></svg>">
</head>
<body>

  <!-- ========================================================
       PERSISTENT NAVIGATION BAR
       Strictly contains ONLY: Map, My Car, My Spaces, Points, Profile
       ======================================================== -->
  <header class="site-header">
    <nav class="navbar" aria-label="Main Navigation">
      <a href="index.php" class="brand">
        <div class="brand-icon">🚗</div>
        <div class="brand-text">
          <span class="brand-title">JODROD</span>
          <span class="brand-tagline">Flood Parking Community</span>
        </div>
      </a>

      <!-- Strictly: Map, My Car, My Spaces, Points, Profile -->
      <ul class="nav-links">
        <li>
          <a href="#map" class="nav-link" id="nav-map">
            <span>🗺️</span> Map
          </a>
        </li>
        <li>
          <a href="#my-car" class="nav-link" id="nav-my-car">
            <span>🚙</span> My Car
          </a>
        </li>
        <li>
          <a href="#my-spaces" class="nav-link" id="nav-my-spaces">
            <span>🅿️</span> My Spaces
          </a>
        </li>
        <li>
          <a href="#points" class="nav-link" id="nav-points">
            <span>⭐</span> Points
          </a>
        </li>
        <li>
          <a href="#profile" class="nav-link" id="nav-profile">
            <span>👤</span> Profile
          </a>
        </li>
      </ul>
    </nav>
  </header>

  <!-- ========================================================
       MAIN CONTENT CONTAINER
       ======================================================== -->
  <main class="container">

    <!-- HOME / OVERVIEW SECTION (DEFAULT ACTIVE) -->
    <div id="section-home">

      <!-- Hero Banner -->
      <section class="hero-card">
        <div class="hero-badge">🌊 Real-World Disaster Relief &bull; Flood Parking Network</div>
        <h1 class="brand-font hero-title">
          เครือข่ายแบ่งปันที่จอดรถปลอดภัยช่วงน้ำท่วม
        </h1>
        <p class="hero-desc">
          เมื่อเกิดอุทกภัย การย้ายรถขึ้นที่สูงอย่างทันท่วงทีช่วยลดความเสียหายมหาศาล 
          JodRod เชื่อมโยงเจ้าของรถที่ต้องการที่จอดหนีน้ำ กับเพื่อนร่วมชุมชนที่มีพื้นที่จอดรถปลอดภัยพร้อมแบ่งปัน
        </p>
      </section>

      <!-- Two Main Actions -->
      <section aria-label="Main Community Actions">
        <div class="actions-grid">
          
          <!-- Action 1: Find Parking -->
          <article class="action-card find-parking" id="card-find-parking">
            <div>
              <div class="action-card-header">
                <div class="action-card-icon">🔍</div>
                <div>
                  <span class="action-badge">FOR DRIVERS</span>
                  <h2 class="action-title">1. Find Parking (หาที่จอดรถ)</h2>
                </div>
              </div>
              <p class="action-desc">
                สำหรับผู้ที่มีรถยนต์และกำลังเผชิญกับความเสี่ยงน้ำท่วม ค้นหาพื้นที่จอดรถที่สูงพ้นน้ำ อาคาร ลานบ้าน หรือพื้นที่ปลอดภัยใกล้คุณ
              </p>
              <ul class="action-points-list">
                <li>✅ ตรวจสอบพิกัดและพื้นที่จอดรถปลอดภัยบนแผนที่</li>
                <li>✅ ดูจำนวนช่องจอดที่ว่างจริงในแต่ละจุด</li>
                <li>✅ ส่งคำขอจอดรถและรับการยืนยันจากเจ้าของพื้นที่</li>
              </ul>
            </div>
            <div>
              <a href="#map" class="pixel-btn pixel-btn-blue action-btn" style="width: 100%;" id="btn-action-find">
                <span>🚗</span> เริ่มต้นค้นหาที่จอดรถ (Find Parking)
              </a>
            </div>
          </article>

          <!-- Action 2: Offer Parking Space -->
          <article class="action-card offer-parking" id="card-offer-parking">
            <div>
              <div class="action-card-header">
                <div class="action-card-icon">🅿️</div>
                <div>
                  <span class="action-badge">FOR PARKING OWNERS</span>
                  <h2 class="action-title">2. Offer Parking Space (แบ่งปันที่จอดรถ)</h2>
                </div>
              </div>
              <p class="action-desc">
                สำหรับผู้ที่มีลานบ้าน เนินสูง ลานธุรกิจ หรือพื้นที่ปลอดภัยที่น้ำไม่ท่วม ร่วมเป็นพลังชุมชนเปิดรับรถของเพื่อนบ้านเข้าจอด
              </p>
              <ul class="action-points-list">
                <li>✅ ระบุตำแหน่งพิกัดและชื่อจุดจอดของคุณ</li>
                <li>✅ กำหนดจำนวนรถที่สามารถรองรับได้ (Capacity)</li>
                <li>✅ ตรวจสอบและกดยืนยันคำขอจอดรถจากเพื่อนบ้าน</li>
              </ul>
            </div>
            <div>
              <a href="#my-spaces" class="pixel-btn pixel-btn-green action-btn" style="width: 100%;" id="btn-action-offer">
                <span>🅿️</span> ลงทะเบียนแบ่งปันพื้นที่ (Offer Parking)
              </a>
            </div>
          </article>

        </div>
      </section>

      <!-- Realistic Community Concept & Flow -->
      <section class="concept-section">
        <h3 class="section-title">
          <span>🔄</span> ขั้นตอนการทำงานของชุมชน Flood Parking
        </h3>
        <p class="section-subtitle">
          ขั้นตอนที่โปร่งใสและสร้างสรรค์ ผ่านระบบการยืนยันร่วมกัน (Mutual Confirmation)
        </p>

        <div class="steps-grid">
          <div class="step-box">
            <div class="step-num">STEP 01</div>
            <div class="step-heading">ค้นหาหรือลงพื้นที่</div>
            <div class="step-text">ผู้มีพื้นที่เปิดรับจอดระบุพิกัด ส่วนเจ้าของรถค้นหาจุดที่ปลอดภัยบนแผนที่จริง</div>
          </div>
          <div class="step-box">
            <div class="step-num">STEP 02</div>
            <div class="step-heading">ส่งคำขอจอดรถ</div>
            <div class="step-text">เจ้าของรถส่งคำขอจอดระบุข้อมูลรถ และรอเจ้าของสถานที่กดยอมรับ</div>
          </div>
          <div class="step-box">
            <div class="step-num">STEP 03</div>
            <div class="step-heading">ยืนยันการเข้าจอด</div>
            <div class="step-text">เมื่อนำรถเข้าจอดจริง ทั้งสองฝ่ายทำการยืนยันร่วมกัน (Mutual Confirmation)</div>
          </div>
          <div class="step-box">
            <div class="step-num">STEP 04</div>
            <div class="step-heading">รับคะแนนชุมชน</div>
            <div class="step-text">เมื่อเสร็จสิ้นภารกิจรับคะแนนจิตอาสาเพื่อตอบแทนความช่วยเหลือในชุมชน</div>
          </div>
        </div>
      </section>

    </div>

    <!-- ========================================================
         NAVIGATION TARGET SECTIONS (MODULAR PLACEHOLDERS)
         ======================================================== -->
    
    <!-- 1. Map Section -->
    <section id="section-map" class="nav-content-section">
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
        <h2 class="section-title"><span>🗺️</span> Real-World Flood Parking Map</h2>
        <a href="#home" class="pixel-btn pixel-btn-sm btn-back-home">&larr; กลับหน้าหลัก</a>
      </div>
      <p class="section-subtitle">แผนที่แสดงตำแหน่งพื้นที่จอดรถปลอดภัยช่วงน้ำท่วม</p>
      <div class="placeholder-notice">
        <p style="font-weight: 700; font-size: 1.1rem; margin-bottom: 8px;">📍 Map Layout Ready</p>
        <p>การแสดงผลแผนที่จริงและการเชื่อมต่อพิกัดจะถูกผสานในขั้นตอนถัดไปตามลำดับใน BRIEF.md</p>
      </div>
    </section>

    <!-- 2. My Car Section -->
    <section id="section-my-car" class="nav-content-section">
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
        <h2 class="section-title"><span>🚙</span> My Car & Requests</h2>
        <a href="#home" class="pixel-btn pixel-btn-sm btn-back-home">&larr; กลับหน้าหลัก</a>
      </div>
      <p class="section-subtitle">ข้อมูลยานพาหนะและสถานะคำขอที่จอดรถของคุณ</p>
      <div class="placeholder-notice">
        <p style="font-weight: 700; font-size: 1.1rem; margin-bottom: 8px;">🚙 Driver Dashboard Ready</p>
        <p>ระบบจัดการคำขอจอดรถและการยืนยันเข้าจอดจะพร้อมใช้งานในเฟสคำขอจอดรถ (Phase 2)</p>
      </div>
    </section>

    <!-- 3. My Spaces Section -->
    <section id="section-my-spaces" class="nav-content-section">
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
        <h2 class="section-title"><span>🅿️</span> My Parking Spaces</h2>
        <a href="#home" class="pixel-btn pixel-btn-sm btn-back-home">&larr; กลับหน้าหลัก</a>
      </div>
      <p class="section-subtitle">จัดการพื้นที่จอดรถที่คุณเปิดให้เพื่อนบ้านเข้าจอด</p>
      <div class="placeholder-notice">
        <p style="font-weight: 700; font-size: 1.1rem; margin-bottom: 8px;">🅿️ Parking Spaces Manager Ready</p>
        <p>ระบบ CRUD สำหรับสร้าง แก้ไข และลบพื้นที่จอดรถจะพร้อมทำงานในขั้นตอนการทำ Parking Space CRUD</p>
      </div>
    </section>

    <!-- 4. Points Section -->
    <section id="section-points" class="nav-content-section">
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
        <h2 class="section-title"><span>⭐</span> Community Points</h2>
        <a href="#home" class="pixel-btn pixel-btn-sm btn-back-home">&larr; กลับหน้าหลัก</a>
      </div>
      <p class="section-subtitle">คะแนนจิตอาสาและประวัติการช่วยเหลือเพื่อนร่วมชุมชน</p>
      <div class="placeholder-notice">
        <p style="font-weight: 700; font-size: 1.1rem; margin-bottom: 8px;">⭐ Points Reward System Ready</p>
        <p>ระบบสะสมคะแนนจากการช่วยเหลือสำเร็จจะถูกเชื่อมต่อหลังระบบ Mutual Confirmation</p>
      </div>
    </section>

    <!-- 5. Profile Section -->
    <section id="section-profile" class="nav-content-section">
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
        <h2 class="section-title"><span>👤</span> Community Profile</h2>
        <a href="#home" class="pixel-btn pixel-btn-sm btn-back-home">&larr; กลับหน้าหลัก</a>
      </div>
      <p class="section-subtitle">ข้อมูลโปรไฟล์บทบาทในชุมชน (Driver / Parking Owner)</p>
      <div class="placeholder-notice">
        <p style="font-weight: 700; font-size: 1.1rem; margin-bottom: 8px;">👤 Profile Overview Ready</p>
        <p>ข้อมูลโปรไฟล์ผู้ใช้งานในชุมชน Flood Parking</p>
      </div>
    </section>

  </main>

  <!-- ========================================================
       FOOTER
       ======================================================== -->
  <footer>
    <p>
      🚗 <strong>JodRod (จอดรถ) — Flood Parking Community</strong> &bull; Civic Disaster Relief Initiative<br>
      ระบบช่วยเหลือและแบ่งปันที่จอดรถพ้นน้ำท่วมเพื่อความปลอดภัยของทุกคนในชุมชน
    </p>
  </footer>

  <!-- ========================================================
       PERSISTENT NAVIGATION CONTROLLER
       ======================================================== -->
  <script>
    document.addEventListener('DOMContentLoaded', () => {
      const navLinks = {
        '#map': document.getElementById('nav-map'),
        '#my-car': document.getElementById('nav-my-car'),
        '#my-spaces': document.getElementById('nav-my-spaces'),
        '#points': document.getElementById('nav-points'),
        '#profile': document.getElementById('nav-profile')
      };

      const sections = {
        '#home': document.getElementById('section-home'),
        '#map': document.getElementById('section-map'),
        '#my-car': document.getElementById('section-my-car'),
        '#my-spaces': document.getElementById('section-my-spaces'),
        '#points': document.getElementById('section-points'),
        '#profile': document.getElementById('section-profile')
      };

      function handleRoute() {
        const hash = window.location.hash || '#home';

        // Update nav active states
        Object.keys(navLinks).forEach(key => {
          if (navLinks[key]) {
            if (key === hash) {
              navLinks[key].classList.add('active');
            } else {
              navLinks[key].classList.remove('active');
            }
          }
        });

        // Show/hide sections
        if (hash === '#home' || !sections[hash]) {
          sections['#home'].style.display = 'block';
          Object.keys(sections).forEach(key => {
            if (key !== '#home' && sections[key]) {
              sections[key].classList.remove('active');
            }
          });
        } else {
          sections['#home'].style.display = 'none';
          Object.keys(sections).forEach(key => {
            if (key !== '#home' && sections[key]) {
              if (key === hash) {
                sections[key].classList.add('active');
              } else {
                sections[key].classList.remove('active');
              }
            }
          });
        }
      }

      window.addEventListener('hashchange', handleRoute);
      handleRoute();

      // Back to home buttons
      document.querySelectorAll('.btn-back-home').forEach(btn => {
        btn.addEventListener('click', (e) => {
          e.preventDefault();
          window.location.hash = '#home';
        });
      });
    });
  </script>

</body>
</html>
