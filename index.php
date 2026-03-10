<?php session_start(); ?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <!-- Title that appears in search results -->
  <title>HOME | SENOAMADI_BANK</title>
  <!-- Short description that appears under the title in Google -->
  <meta name="description" content="Get access to reliable digital banking, financial partner, and technology-driven
            banking services." />
  <!-- Keywords (optional but still useful) -->
  <meta name="keywords" content="Senoamadi bank, loan, Investment, technology-driven
            banking services, secure future, Judge" />
  <!-- Author -->
  <meta name="author" content="Judge Tshwarelo" />
  <!-- Favicon (small icon that appears next to your site title) -->
  <link rel="icon" type="image/png" href="SENOAMADI_BANK.png" />

  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css">

  <!-- Boxicons for menu icons -->
  <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">

  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
      font-family: "Poppins", Arial, sans-serif;
      scroll-behavior: smooth;
    }

    body {
      background: #6c6c6c6b;
      color: #fff;
    }

    /* ===== HEADER ===== */
    header {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      background: #0A1A2F;
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 10px 40px;
      z-index: 1000;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
    }

    .logo {
      font-size: 2rem;
      font-weight: 700;
      color: #fff;
      text-transform: uppercase;
      cursor: pointer;
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .logo img {
      width: 45px;
      border-radius: 10px;
    }

    .logo:hover {
      color: #f1f1f1;
      transform: scale(1.05);
      transition: 0.3s;
    }

    /* ===== NAVIGATION ===== */
    nav ul {
      display: flex;
      list-style: none;
      gap: 25px;
    }

    nav ul li a {
      color: #D4AF37;
      text-decoration: none;
      font-size: 18px;
      font-weight: 500;
      position: relative;
      padding: 5px 0;
      transition: color 0.3s;
    }

    nav ul li a::after {
      content: "";
      position: absolute;
      left: 0;
      bottom: -3px;
      width: 0%;
      height: 2px;
      background: #D4AF37;
      transition: width 0.3s;
    }

    nav ul li a:hover::after {
      width: 100%;
    }

    nav ul li a:hover {
      color: #fff;
    }

    /* ===== ACTIVE LINK ===== */
    nav ul li a.active {
      color: #fff;
      font-weight: 600;
    }

    /* ===== HAMBURGER MENU ===== */
    .menu-toggle {
      display: none;
      font-size: 32px;
      color: #fff;
      cursor: pointer;
    }

    /* ===== RESPONSIVE ===== */
    @media (max-width: 768px) {
      header {
        padding: 10px 25px;
      }

      nav ul {
        position: absolute;
        top: 60px;
        right: 0;
        width: 100%;
        background: #0A1A2F;
        flex-direction: column;
        align-items: center;
        gap: 15px;
        padding: 20px 0;
        display: none;
      }

      nav ul.active {
        display: flex;
        animation: slideDown 0.3s ease-in;
      }

      @keyframes slideDown {
        from {
          opacity: 0;
          transform: translateY(-15px);
        }

        to {
          opacity: 1;
          transform: translateY(0);
        }
      }

      .menu-toggle {
        display: block;
      }
    }

    /* ===== MAIN CONTENT ===== */
    section {
      min-height: 100vh;
      padding: 90px 40px;
      display: flex;
      flex-direction: column;
      justify-content: center;
      align-items: center;
    }

    #home {
      background: url("SB_BUILDING.png") no-repeat center/cover;
      text-align: center;
    }

    #home .content {
      background: rgba(0, 0, 0, 0.6);
      padding: 30px;
      border-radius: 15px;
      max-width: 1000px;
    }

    #home h1 {
      font-size: 3em;
      margin-bottom: 15px;
    }

    #home p {
      font-size: 1.3em;
    }

    .chips {
      display: flex;
      flex-wrap: wrap;
      gap: 10px;
      margin-top: 16px;
      justify-content: center;
    }

    .chip {
      padding: 6px 10px;
      border-radius: 10px;
      background: #0A1A2F;
      border: 2px solid #D4AF37;
      font-size: 13px;
    }

    .cta {
      display: flex;
      gap: 12px;
      margin-top: 18px;
      justify-content: center;
      flex-wrap: wrap;
    }

    .btn {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 10px 14px;
      border-radius: 12px;
      background: #D4AF37;
      color: #0A1A2F;
      font-weight: 700;
      border: none;
      cursor: pointer;
      text-decoration: none;
      transition: 0.3s;
    }

    .btn:hover {
      transform: scale(1.05);
    }

    .btn.ghost {
      background: transparent;
      border: 2px solid #fff;
      color: #fff;
    }

    /* ===== ABOUTS, PROJECTS, CONTACT (simplified for brevity) ===== */
    .projects,
    .abouts {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
      gap: 20px;
      width: 100%;
    }

    .project,
    .about {
      background: #111;
      border-radius: 12px;
      padding: 16px;
      text-align: center;
    }

    .project img {
      max-width: 100%;
      border-radius: 12px;
      margin-bottom: 10px;
    }

    #contact {
      background: linear-gradient(135deg, #0A1A2F, #1C1F26, #D4AF37, #1C1F26, #0A1A2F);
      color: #000;
    }

    .contact {
      display: flex;
      flex-wrap: wrap;
      gap: 20px;
      justify-content: center;
      align-items: flex-start;
      padding: 20px;
    }

    .contact form {
      flex: 1;
      min-width: 280px;
      display: flex;
      flex-direction: column;
      gap: 10px;
    }

    .contact form input,
    .contact form textarea {
      background: #f0f0f0;
      border: 1px solid #ccc;
      color: #000;
      border-radius: 8px;
      padding: 12px;
    }

    .contact form textarea {
      min-height: 120px;
    }

    .contact img {
      flex: 1;
      max-width: 400px;
      border-radius: 12px;
    }

    .accent {
      color: #D4AF37;
    }

    /* ===== ABOUT US ===== */
    #abouts {
      background: linear-gradient(135deg, #0A1A2F, #1C1F26, #D4AF37, #1C1F26, #0A1A2F);
      color: #000;
      width: 100%;
    }

    .abouts {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
      gap: 20px;
      width: 100%;
    }

    .about {
      background: #6c6c6c6b;
      border-radius: 12px;
      padding: 16px;
      text-align: center;
    }

    .pill {
      display: inline-block;
      font-size: 15px;
      padding: 4px 8px;
      border-radius: 999px;
      border: 1px solid rgba(0, 0, 0, 0.3);
      margin: 2px;
    }

    /* ===== PROJECTS ===== */
    .projects {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
      gap: 20px;
      width: 100%;
      max-width: 1000px;
    }

    .project {
      background: #111;
      border-radius: 12px;
      padding: 16px;
      text-align: center;
    }

    .project img {
      max-width: 100%;
      border-radius: 12px;
      margin-bottom: 10px;
    }
  </style>
</head>

<body>
  <header>
    <div class="logo">
      <img src="SENOAMADI_BANK.png" alt="LOGO">
      <span>SENOAMADI FINANCIALS SERVICES</span>
    </div>
    <i class="bx bx-menu menu-toggle" id="menu-toggle"></i>
    <nav>
      <ul id="nav-links">
        <li><a href="#home" class="active">HOME</a></li>
        <li><a href="#projects">SERVICES</a></li>
        <li><a href="#abouts">ABOUT US</a></li>
        <li><a href="#team">TEAM</a></li>
        <li><a href="#contact">CONTACT</a></li>
      </ul>
    </nav>
  </header>

  <main>
    <!-- HOME -->
    <section id="home">
      <div class="content">
        <p>Welcome to</p>
        <h1><i><span class="accent"><b>SENOAMADI</b></span> FINANCIALS SERVICES</i></h1>
        <p>Where trust meets innovation  —  <i>Empower Your Future, Securely</i> — </p>
        <div class="chips">
          <span class="chip">PERSONAL / BUSINESS</span>
          <span class="chip">LOANS / INVESTMENT</span>
            <span class="chip"><a href="privacy-policy.html" title="Privacy-Policy">Privacy-Policy</a></span>
            <span class="chip"><a href="terms-and-conditions.html" title="Terms & Conditions">Terms & Conditions</a></span>
        </div>
        <div class="cta">
          <button class="btn" onclick="window.location.href='index2.php?form=register'">Open Account</button>
          <button class="btn ghost" onclick="window.location.href='index2.php?form=login'">Sign In</button>
        </div>
      </div>
    </section>

    <!-- Add your other sections (Services, Abouts, Team, Contact) unchanged -->
    <!-- SERVICES -->
    <section id="projects">
      <h2 style="text-align: center; margin-bottom: 20px; font-size: 40px; color: #0A1A2F;">SERVICES</h2>
      <div class="projects">
        <div class="project">
          <img src="invest2.avif" alt="Loans & Investment graph" />
          <h3>Loans & Investment</h3>
          <p>Get up to R20 000 worth of loans & Investment, with as low as
            40% interest rate and Invest as much as you can for
            returns of up to 40% interest.
          </p>
        </div>

        <div class="project">
          <img src="business.avif" alt="Business Mangement" />
          <h3>Personal & Business Mangement</h3>
          <p>Manage your business with ease, and get specialist support.
            Use our secure solutions to help manage your business's electronic payments.
          </p>
        </div>

        <div class="project">
          <img src="money.jpg" alt="Monthly saving fees" />
          <h3>Save up to 50% on monthly fees</h3>
          <p>Why pay more? Get low monthly fees, that won't even
            borther your account.</p>
        </div>
      </div>
    </section>

    <!-- ABOUT US -->
    <section id="abouts">
      <h2 style="text-align: center; margin-bottom: 20px; font-size: 40px; color: #0A1A2F;">ABOUT US</h2>
      <h2 style="text-align:center;margin-bottom:20px;">MISSION, VISION & VALUES</h2>
      <div class="abouts">
        <div class="about">
          <h3>MISSION</h3>
          <p>We are committed to empowering individuals
            and businesses by providing reliable,
            transparent, and accessible financial solutions.
            We aim to simplify banking through digital innovation
            while maintaining the highest standards of integrity
            and customer care.</p>
        </div>
        <div class="about">
          <h3>VISION</h3>
          <p>To become South Africa’s most trusted and innovative
            financial partner, driving inclusive growth
            through smart, secure, and technology-driven
            banking services.</p>
        </div>
        <div class="about">
          <h3>VALUES</h3>
          <div>
            <span class="pill">Integrity</span>
            <span class="pill">Innovation</span>
            <span class="pill">Excellence</span>
            <span class="pill">Customer-Centricity</span>
            <span class="pill">Accountability</span>
            <span class="pill">Inclusivity</span>
          </div>
        </div>
      </div>

      <h2 style="text-align: center; margin-bottom: 20px; margin-top: 30px;">GOALS & OBJECTIVES</h2>
      <div class="abouts">
        <div class="about">
          <h3>GOALS</h3>
          <div>
            <span class="pill">Expand digital banking accessibility
              across South Africa and Africa.</span>
            <span class="pill">Enhance customer experience through
              AI-driven financial services.</span>
            <span class="pill">Build a secure, transparent ecosystem
              for personal and business banking.</span>
            <span class="pill">Support community development through
              education and financial literacy programs.</span>
            <span class="pill">Achieve sustainable growth through
              ethical banking and green finance initiatives.</span>
          </div>
        </div>
        <div class="about">
          <h3>OBJECTIVES</h3>
          <div>
            <span class="pill">Launch a fully digital banking app and portal by the end of the fiscal year.</span>
            <span class="pill">Maintain a customer satisfaction rate above 90%.</span>
            <span class="pill">Reduce transaction processing time by 50% through automation.</span>
            <span class="pill">Partner with educational and entrepreneurial programs to empower youth.</span>
            <span class="pill">Achieve carbon-neutral operations within five years.</span>
          </div>
        </div>
      </div>
      </div>
    </section>

    <!-- TEAM -->
    <section id="team">
      <h2 style="text-align: center; margin-bottom: 20px; font-size: 40px; color: #0A1A2F;">PRODUCTION TEAM</h2>
      <div class="projects">
        <div class="project">
          <img src="judge.jpg" alt="JUDGE">
          <h3>Mr Senoamadi TJ</h3>
          <div>
            <span class="pill">CEO</span>
            <span class="pill">Database administrator</span>
            <span class="pill">Software intergrater</span>
          </div>
        </div>

        <div class="project">
          <h3>MARKETING</h3>
          <div>
            <span class="pill">VACANT</span>
          </div>

        </div>

        <div class="project">
          <h3>DATA ANALYSIS</h3>
          <div>
            <span class="pill">VACANT</span>
          </div>
        </div>
      </div>
    </section>

    <!-- CONTACT -->
    <section id="contact">
      <h2 style="text-align: center; margin-bottom: 20px; font-size: 40px; color: #0A1A2F;">GET IN TOUCH</h2>
      <div class="contact">
        <form onsubmit="event.preventDefault(); alert('Thanks! We will respond soon.');">
          <input type="text" placeholder="Your name" required />
          <input type="email" placeholder="Email address" required />
          <textarea placeholder="Message" required></textarea>
          <button class="btn" type="submit">Send Message</button>
          <div class="social-links">
            <a href="https://wa.me/+27721448175" target="_blank" class="btn whatsapp"><i class="fab fa-whatsapp"></i> WhatsApp</a>
            <a href="mailto:baloyijudge@gmail.com" class="btn email"><i class="fas fa-envelope"></i> Email</a>
            <a href="https://www.tiktok.com/@instigatoral_prodidy" target="_blank" class="btn tiktok"><i class="fab fa-tiktok"></i> TikTok</a>
          </div>
        </form>
        <img src="SB_CARD.png" alt="Contact">
      </div>
    </section>

    <footer style="background-color: #0A1A2F; color: #fff; padding: 40px 15px; margin-top: -80px; font-family: Arial, sans-serif;">
          <div style="max-width: 1900px; margin: 0 auto; display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; text-align: left;">
            
            <!-- Column 1: Logo + About -->
            <div>
              <h3 style="color: #f1c40f;">Senoamadi Financial Services</h3>
              <p style="font-size: 15px; color: #ddd; line-height: 1.6;">
                Empowering South Africans with fast, secure, and transparent online loan services.  
                Apply, get approved, and grow your dreams — all online.
              </p>
            </div>
        
            <!-- Column 2: Quick Links -->
            <div>
              <h4 style="color: #f1c40f;">Quick Links</h4>
              <ul style="list-style: none; padding: 0;">
                <li><a href="#Home" style="color: #ddd; text-decoration: none;">🏠 Home</a></li>
                <li><a href="#projects" style="color: #ddd; text-decoration: none;">💼 Services</a></li>
                <li><a href="#team" style="color: #ddd; text-decoration: none;">👥 Our Team</a></li>
                <li><a href="privacy-policy.html" style="color: #ddd; text-decoration: none;">🔒 Privacy Policy</a></li>
                <li><a href="terms-and-conditions.html" style="color: #ddd; text-decoration: none;">📜 Terms & Conditions</a></li>
              </ul>
            </div>
        
            <!-- Column 3: Contact Info -->
            <div>
              <h4 style="color: #f1c40f;">Contact Us</h4>
              <p style="font-size: 15px; color: #ddd;">
                📧 <a href="mailto:senoamadigenesisfarm@gmail.com" style="color: #f1c40f; text-decoration: none;">senoamadigenesisfarm@gmail.com</a><br>
                📞 <a href="tel:+27721448175" style="color: #f1c40f; text-decoration: none;">+27 72 144 8175</a><br>
                📍 South Africa, Limpopo, Polokwane, Ga-matlala, Kgomoschool [10313]
              </p>
            </div>
        
            <!-- Column 4: Social Media -->
            <div>
              <h4 style="color: #f1c40f;">Follow Us</h4>
              <div style="margin-top: 10px;">
                <a href="https://facebook.com" target="_blank" style="margin-right: 10px; text-decoration: none; color: #fff; font-size: 24px;">📘</a>
                <a href="https://wa.me/+27721448175" target="_blank" style="margin-right: 10px; text-decoration: none; color: #25D366; font-size: 24px;">💬</a>
                <a href="https://linkedin.com" target="_blank" style="margin-right: 10px; text-decoration: none; color: #0077b5; font-size: 24px;">🔗</a>
                <a href="https://instagram.com" target="_blank" style="text-decoration: none; color: #E4405F; font-size: 24px;">📸</a>
              </div>
            </div>
        
          </div>
        
          <hr style="border: none; border-top: 1px solid #444; margin: 30px 0;">
        
          <div style="text-align: center; font-size: 14px; color: #bbb;">
            &copy; <script>document.write(new Date().getFullYear());</script> Senoamadi Bank Services. All rights reserved.<br>
            Built with ❤️ by Judge Tshwarelo | Secure 🔒 | Powered by InfinityFree Hosting
          </div>
        </footer>
  </main>

  <script>
    // Toggle menu
    const menuToggle = document.getElementById("menu-toggle");
    const navLinks = document.getElementById("nav-links");

    menuToggle.addEventListener("click", () => {
      navLinks.classList.toggle("active");
      menuToggle.classList.toggle("bx-x");
    });

    // Highlight current section
    const sections = document.querySelectorAll("section");
    const navItems = document.querySelectorAll("nav ul li a");

    window.addEventListener("scroll", () => {
      let current = "";
      sections.forEach((section) => {
        const sectionTop = section.offsetTop - 150;
        if (scrollY >= sectionTop) current = section.getAttribute("id");
      });
      navItems.forEach((a) => {
        a.classList.remove("active");
        if (a.getAttribute("href").includes(current)) a.classList.add("active");
      });
    });
  </script>p

</body>

</html>

<?php
if (isset($_GET['form'])) {
  $formType = $_GET['form'];
  if ($formType === 'login') {
    include 'login_form.php';
  } elseif ($formType === 'register') {
    include 'register_form.php';
  }
  exit;
}
?>