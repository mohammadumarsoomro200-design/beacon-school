<?php require_once 'config/config.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Directors' Reviews | The New Beacon School System</title>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&family=Playfair+Display:wght@700;800&display=swap" rel="stylesheet">
  <style>
    body { font-family: 'Plus Jakarta Sans', sans-serif; background: #f8fafc; color: #1e293b; margin: 0; padding: 40px 20px; }
    .page-container { max-width: 900px; margin: 0 auto; }
    .back-btn { text-decoration: none; color: #0b1d3a; font-weight: 700; font-size: 14px; display: inline-flex; align-items: center; gap: 6px; margin-bottom: 25px; background: #ffffff; padding: 8px 16px; border-radius: 8px; border: 1px solid #cbd5e1; transition: 0.3s; }
    .back-btn:hover { background: #d4af37; color: #0b1d3a; border-color: #d4af37; }
    .main-title { font-family: 'Playfair Display', serif; color: #0b1d3a; font-size: 34px; text-align: center; margin-bottom: 40px; }
    
    .director-card { background: #ffffff; padding: 35px; border-radius: 20px; margin-bottom: 30px; box-shadow: 0 10px 30px rgba(0,0,0,0.05); border: 1px solid #e2e8f0; display: grid; grid-template-columns: 220px 1fr; gap: 30px; align-items: center; }
    .director-img { width: 100%; height: 230px; object-fit: cover; border-radius: 14px; border: 3px solid #d4af37; }
    .role-badge { color: #9a6b00; background: rgba(212, 175, 55, 0.15); font-weight: 800; font-size: 11px; letter-spacing: 1px; padding: 5px 12px; border-radius: 20px; display: inline-block; margin-bottom: 10px; }
    .director-card h2 { font-family: 'Playfair Display', serif; color: #0b1d3a; margin: 0 0 12px 0; font-size: 24px; }
    .director-card p { line-height: 1.8; font-size: 15px; color: #475569; margin-bottom: 15px; }
    .signature { font-weight: 700; color: #0b1d3a; font-size: 15px; }

    @media (max-width: 650px) {
      .director-card { grid-template-columns: 1fr; text-align: center; }
      .director-img { height: 200px; }
    }
  </style>
</head>
<body>

  <div class="page-container">
    <a href="index.php#facilities" class="back-btn">← Back to Main Page</a>
    
    <h1 class="main-title">Directors' Vision & Reviews</h1>

    <!-- Director General Card -->
    <div class="director-card">
      <img src="assets/images/dg.jpg" alt="Director General" class="director-img">
      <div>
        <span class="role-badge">DIRECTOR GENERAL (DG)</span>
        <h2>Director General's Review</h2>
        <p>"We focus on holistic development, ensuring that our curriculum balances academic rigor with creative thinking, discipline, and strong community values for lifelong learning."</p>
        <div class="signature">— Director General Name</div>
      </div>
    </div>

    <!-- Assistant Director General Card -->
    <div class="director-card">
      <img src="assets/images/adg.JPG" alt="Assistant Director General" class="director-img">
      <div>
        <span class="role-badge">ASSISTANT DIRECTOR GENERAL (ADG)</span>
        <h2>Assistant Director General's Vision</h2>
        <p>"Through continuous innovation in our teaching methodology and close parent-school collaboration, we create a supportive atmosphere where students can truly excel."</p>
        <div class="signature">— Assistant Director General Name</div>
      </div>
    </div>

  </div>

</body>
</html>