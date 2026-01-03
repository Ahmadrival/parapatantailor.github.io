<?php
session_start();

// Hapus semua session
session_destroy();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logout - SIM Parapatan Tailor</title>
    <link rel="shortcut icon" href="../parapatan_tailor/assets/images/logoterakhir.png" type="image/x-icon" />
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
    :root {
        --sidebar-purple: #4f46e5;        /* Warna ungu sidebar utama */
        --sidebar-purple-light: #7c3aed;  /* Warna ungu sidebar sekunder */
        --sidebar-purple-dark: #4338ca;   /* Warna ungu lebih gelap */
        --light-purple: #e0e7ff;          /* Ungu muda untuk background */
        --bright-purple: #8b5cf6;         /* Ungu cerah untuk aksen */
        --vibrant-purple: #a78bfa;        /* Ungu lebih terang */
        --white: #ffffff;
        --light-gray: #f8fafc;
        --medium-gray: #e2e8f0;
        --dark-gray: #64748b;
        --text-dark: #1e293b;
        --text-light: #ffffff;
        --glow-purple: 0 0 20px rgba(79, 70, 229, 0.4),
                     0 0 40px rgba(124, 58, 237, 0.2),
                     0 0 60px rgba(79, 70, 229, 0.1);
    }
    
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
        font-family: 'Segoe UI', 'Arial', sans-serif;
    }
    
    body {
        background-color: #f1f5f9;
        background-image: 
            radial-gradient(circle at 90% 10%, rgba(79, 70, 229, 0.08) 0%, transparent 25%),
            radial-gradient(circle at 10% 90%, rgba(124, 58, 237, 0.08) 0%, transparent 25%);
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 20px;
        position: relative;
        overflow-x: hidden;
    }
    
    /* Animated background pattern */
    .pattern-background {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-image: 
            radial-gradient(circle at 25% 25%, rgba(79, 70, 229, 0.05) 0%, transparent 50%),
            radial-gradient(circle at 75% 75%, rgba(124, 58, 237, 0.05) 0%, transparent 50%);
        z-index: -1;
        opacity: 0.6;
    }
    
    /* Decorative elements */
    .decoration {
        position: fixed;
        width: 100%;
        height: 100%;
        pointer-events: none;
        z-index: -1;
    }
    
    .deco-circle {
        position: absolute;
        border-radius: 50%;
        background: radial-gradient(circle, var(--sidebar-purple), transparent);
        opacity: 0.08;
        animation: float 25s infinite linear;
    }
    
    @keyframes float {
        0%, 100% {
            transform: translate(0, 0) rotate(0deg);
        }
        33% {
            transform: translate(30px, -30px) rotate(120deg);
        }
        66% {
            transform: translate(-20px, 20px) rotate(240deg);
        }
    }
    
    /* Logout Container - Matching dengan login.php */
    .logout-container {
        display: flex;
        width: 100%;
        max-width: 500px;
        min-height: 400px;
        background: linear-gradient(145deg, var(--light-purple), var(--white));
        border-radius: 16px; /* Sama dengan sidebar */
        overflow: hidden;
        position: relative;
        box-shadow: 
            0 20px 40px rgba(79, 70, 229, 0.15),
            0 10px 25px rgba(124, 58, 237, 0.1),
            0 5px 15px rgba(79, 70, 229, 0.05),
            var(--glow-purple);
        transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        border: 1px solid rgba(79, 70, 229, 0.1);
        animation: fadeIn 0.8s ease-out;
    }
    
    .logout-container:hover {
        box-shadow: 
            0 25px 50px rgba(79, 70, 229, 0.2),
            0 15px 35px rgba(124, 58, 237, 0.15),
            0 8px 20px rgba(79, 70, 229, 0.1),
            0 0 30px rgba(79, 70, 229, 0.3);
        transform: translateY(-5px);
    }
    
    .logout-container::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(90deg, 
            transparent, 
            var(--sidebar-purple), 
            var(--sidebar-purple-light), 
            var(--sidebar-purple), 
            transparent);
        animation: scanline 4s linear infinite;
        box-shadow: 0 0 12px rgba(79, 70, 229, 0.5);
        z-index: 2;
    }
    
    @keyframes scanline {
        0% {
            background-position: -200px 0;
        }
        100% {
            background-position: 200px 0;
        }
    }
    
    /* Logo Container */
    .logo-container {
        text-align: center;
        margin-bottom: 25px;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 15px;
    }
    
    .logo-image {
        width: 85px;
        height: 85px;
        border-radius: 12px; /* Sama dengan sidebar */
        background: linear-gradient(135deg, var(--sidebar-purple), var(--sidebar-purple-light));
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 15px;
        border: 2px solid rgba(255, 255, 255, 0.3); /* Sama dengan sidebar */
        box-shadow: 
            0 8px 25px rgba(79, 70, 229, 0.4),
            0 5px 15px rgba(124, 58, 237, 0.3),
            inset 0 1px 0 rgba(255, 255, 255, 0.5);
        transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        position: relative;
        overflow: hidden;
    }
    
    .logo-image:hover {
        transform: scale(1.08) rotate(5deg);
        box-shadow: 
            0 12px 35px rgba(79, 70, 229, 0.6),
            0 8px 20px rgba(124, 58, 237, 0.5),
            var(--glow-purple),
            inset 0 1px 0 rgba(255, 255, 255, 0.7);
    }
    
    .logo-image img {
        max-width: 100%;
        max-height: 100%;
        object-fit: contain;
        border-radius: 8px;
        filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.1));
    }
    
    .logo-fallback {
        width: 100%;
        height: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        background: linear-gradient(135deg, var(--sidebar-purple), var(--sidebar-purple-light));
        border-radius: 8px;
    }
    
    .logo-text {
        font-size: 2.5rem;
        font-weight: 900;
        color: white;
        text-shadow: 
            2px 2px 4px rgba(0, 0, 0, 0.2),
            0 0 10px rgba(255, 255, 255, 0.3);
    }
    
    .logo-title {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 6px;
    }
    
    .brand-name {
        color: var(--sidebar-purple);
        font-size: 26px;
        font-weight: 800;
        letter-spacing: 2.5px;
        text-transform: uppercase;
        text-shadow: 
            0 2px 4px rgba(79, 70, 229, 0.2);
        position: relative;
        display: inline-block;
        padding-bottom: 8px;
    }
    
    .brand-name::after {
        content: '';
        position: absolute;
        bottom: 0;
        left: 50%;
        transform: translateX(-50%);
        width: 50px;
        height: 3px;
        background: linear-gradient(90deg, 
            transparent, 
            var(--sidebar-purple), 
            transparent);
        border-radius: 2px;
    }
    
    .brand-subtitle {
        color: var(--dark-gray);
        font-size: 14px;
        font-weight: 500;
        letter-spacing: 1.2px;
        opacity: 0.8;
    }
    
    /* Content Styles */
    .logout-content {
        padding: 40px 35px;
        width: 100%;
        text-align: center;
        position: relative;
        background: linear-gradient(135deg, 
            rgba(255, 255, 255, 0.95) 0%, 
            rgba(255, 255, 255, 0.98) 100%);
        backdrop-filter: blur(20px);
    }
    
    /* Success Icon */
    .logout-icon {
        width: 100px;
        height: 100px;
        background: linear-gradient(135deg, 
            rgba(79, 70, 229, 0.1) 0%, 
            rgba(124, 58, 237, 0.2) 100%);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 1.5rem;
        box-shadow: 
            0 12px 25px rgba(79, 70, 229, 0.4),
            inset 0 0 30px rgba(124, 58, 237, 0.1);
        animation: successPulse 2s infinite ease-in-out;
        border: 2px solid rgba(79, 70, 229, 0.4);
        position: relative;
        overflow: hidden;
    }
    
    @keyframes successPulse {
        0%, 100% {
            transform: scale(1);
            box-shadow: 
                0 12px 25px rgba(79, 70, 229, 0.4),
                inset 0 0 30px rgba(124, 58, 237, 0.1);
        }
        50% {
            transform: scale(1.05);
            box-shadow: 
                0 15px 30px rgba(79, 70, 229, 0.6),
                inset 0 0 40px rgba(124, 58, 237, 0.2);
        }
    }
    
    .logout-icon i {
        font-size: 3.5rem;
        color: var(--sidebar-purple-light);
        animation: checkPop 0.6s ease-out forwards;
        text-shadow: 
            0 0 20px rgba(79, 70, 229, 0.5),
            0 0 40px rgba(124, 58, 237, 0.3);
    }
    
    @keyframes checkPop {
        0% { transform: scale(0); opacity: 0; }
        80% { transform: scale(1.2); opacity: 1; }
        100% { transform: scale(1); }
    }
    
    /* Text Styling */
    .logout-title {
        color: var(--sidebar-purple-light);
        font-weight: 800;
        font-size: 2.2rem;
        margin-bottom: 0.5rem;
        letter-spacing: -0.5px;
        text-shadow: 
            0 0 10px rgba(79, 70, 229, 0.2),
            0 0 20px rgba(124, 58, 237, 0.1);
    }
    
    .logout-subtitle {
        color: var(--sidebar-purple);
        font-size: 1.1rem;
        margin-bottom: 1rem;
        font-weight: 500;
        opacity: 0.9;
    }
    
    .logout-message {
        color: var(--text-dark);
        font-size: 1.1rem;
        line-height: 1.6;
        margin: 1.5rem 0;
        padding: 0 1rem;
        opacity: 0.9;
    }
    
    .highlight-brand {
        color: var(--sidebar-purple);
        font-weight: 700;
        text-shadow: 
            0 0 10px rgba(79, 70, 229, 0.2);
    }
    
    /* Button Styling - Gradient sama dengan sidebar */
    .btn-login {
        width: 100%;
        padding: 16px;
        background: linear-gradient(135deg, 
            var(--sidebar-purple) 0%, 
            var(--sidebar-purple-light) 100%);
        border: none;
        border-radius: 12px; /* Sama dengan sidebar */
        color: white;
        font-size: 1.1rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 1px;
        cursor: pointer;
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
        margin-top: 0.5rem;
        text-decoration: none;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        box-shadow: 
            0 6px 20px rgba(79, 70, 229, 0.4),
            0 3px 12px rgba(124, 58, 237, 0.3),
            inset 0 1px 0 rgba(255, 255, 255, 0.2);
    }
    
    .btn-login:hover {
        background: linear-gradient(135deg, 
            var(--sidebar-purple-dark) 0%, 
            var(--sidebar-purple) 100%);
        transform: translateY(-3px);
        box-shadow: 
            0 12px 30px rgba(79, 70, 229, 0.6),
            0 8px 20px rgba(124, 58, 237, 0.5),
            var(--glow-purple),
            inset 0 1px 0 rgba(255, 255, 255, 0.3);
        color: white;
    }
    
    .btn-login:active {
        transform: translateY(-1px);
    }
    
    .btn-login::before {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, 
            transparent, 
            rgba(255, 255, 255, 0.2), 
            transparent);
        transition: left 0.5s;
    }
    
    .btn-login:hover::before {
        left: 100%;
    }
    
    .btn-login i {
        font-size: 1.2rem;
    }
    
    /* Countdown Styles */
    .countdown-container {
        margin-top: 1.5rem;
        padding-top: 1.5rem;
        border-top: 1px solid rgba(79, 70, 229, 0.2);
    }
    
    .countdown-text {
        color: var(--dark-gray);
        font-size: 0.95rem;
        margin-bottom: 0.75rem;
    }
    
    .progress-container {
        width: 100%;
        height: 6px;
        background: rgba(79, 70, 229, 0.1);
        border-radius: 3px;
        margin-bottom: 1rem;
        overflow: hidden;
    }
    
    .progress-bar {
        height: 100%;
        background: linear-gradient(90deg, 
            var(--sidebar-purple) 0%, 
            var(--sidebar-purple-light) 100%);
        border-radius: 3px;
        width: 100%;
        animation: countdownProgress 7s linear forwards;
        box-shadow: 0 0 10px rgba(79, 70, 229, 0.3);
    }
    
    @keyframes countdownProgress {
        from { width: 100%; }
        to { width: 0%; }
    }
    
    .countdown-number {
        font-weight: 700;
        color: var(--sidebar-purple-light);
        text-shadow: 0 0 5px rgba(79, 70, 229, 0.2);
    }
    
    /* Fade in animation */
    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translateY(20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    /* Animation untuk logo */
    @keyframes logoFloat {
        0%, 100% {
            transform: translateY(0) rotate(0deg);
        }
        50% {
            transform: translateY(-8px) rotate(5deg);
        }
    }
    
    .logo-image {
        animation: logoFloat 6s ease-in-out infinite;
    }
    
    /* Responsive Design */
    @media (max-width: 768px) {
        .logout-container {
            max-width: 450px;
        }
        
        .logout-content {
            padding: 35px 30px;
        }
        
        .logout-title {
            font-size: 1.8rem;
        }
        
        .logout-icon {
            width: 90px;
            height: 90px;
        }
        
        .logout-icon i {
            font-size: 3rem;
        }
        
        .brand-name {
            font-size: 24px;
        }
        
        .logo-image {
            width: 75px;
            height: 75px;
        }
    }
    
    @media (max-width: 576px) {
        body {
            padding: 15px;
        }
        
        .logout-container {
            max-width: 400px;
        }
        
        .logout-content {
            padding: 30px 25px;
        }
        
        .logout-title {
            font-size: 1.6rem;
        }
        
        .logout-message {
            font-size: 1rem;
            padding: 0;
        }
        
        .logout-icon {
            width: 80px;
            height: 80px;
        }
        
        .logout-icon i {
            font-size: 2.5rem;
        }
        
        .btn-login {
            padding: 14px;
            font-size: 1rem;
        }
        
        .brand-name {
            font-size: 22px;
        }
        
        .logo-image {
            width: 70px;
            height: 70px;
        }
        
        .logo-text {
            font-size: 2rem;
        }
    }
    
    @media (max-width: 400px) {
        .logout-container {
            max-width: 340px;
        }
        
        .logout-content {
            padding: 25px 20px;
        }
        
        .logout-title {
            font-size: 1.4rem;
        }
        
        .logout-subtitle {
            font-size: 1rem;
        }
        
        .logout-message {
            font-size: 0.95rem;
        }
        
        .logout-icon {
            width: 70px;
            height: 70px;
        }
        
        .logout-icon i {
            font-size: 2rem;
        }
        
        .brand-name {
            font-size: 20px;
        }
        
        .logo-image {
            width: 60px;
            height: 60px;
            padding: 12px;
        }
        
        .btn-login {
            padding: 12px;
            font-size: 0.9rem;
        }
    }
    </style>
</head>
<body>
    <!-- Background Pattern -->
    <div class="pattern-background"></div>
    
    <!-- Decorative Elements -->
    <div class="decoration" id="decoration"></div>
    
    <!-- Logout Container -->
    <div class="logout-container">
        <div class="logout-content">
            <div class="logo-container">
                <div class="logo-image">
                    <div class="logo-fallback">
                        <span class="logo-text">P</span>
                    </div>
                </div>
                <div class="logo-title">
                    <h1 class="brand-name">PARAPATAN TAILOR</h1>
                    <p class="brand-subtitle">Sistem Informasi Manajemen</p>
                </div>
            </div>
            
            <div class="logout-icon">
                <i class="fas fa-sign-out-alt"></i>
            </div>
            
            <h1 class="logout-title">Berhasil Logout</h1>
            <p class="logout-subtitle">Sampai Jumpa Kembali</p>
            
            <div class="logout-message">
                Terima kasih telah menggunakan sistem <span class="highlight-brand">Parapatan Tailor</span>.
                <br>
                Sampai jumpa di lain waktu!
            </div>
            
            <!-- Progress bar untuk visualisasi countdown -->
            <div class="countdown-container">
                <div class="countdown-text">
                    Anda akan diarahkan ke halaman login dalam <span class="countdown-number" id="countdown">7</span> detik
                </div>
                <div class="progress-container">
                    <div class="progress-bar"></div>
                </div>
            </div>
            
            <a href="login.php" class="btn-login">
                <i class="fas fa-sign-in-alt"></i> Kembali ke Login
            </a>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Create decorative circles
            const decoration = document.getElementById('decoration');
            for (let i = 0; i < 6; i++) {
                const circle = document.createElement('div');
                circle.className = 'deco-circle';
                const size = Math.random() * 150 + 50;
                circle.style.width = size + 'px';
                circle.style.height = size + 'px';
                circle.style.left = Math.random() * 100 + '%';
                circle.style.top = Math.random() * 100 + '%';
                circle.style.animationDelay = Math.random() * 20 + 's';
                circle.style.animationDuration = (Math.random() * 20 + 20) + 's';
                decoration.appendChild(circle);
            }
            
            // Countdown timer
            let countdown = 7;
            const countdownElement = document.getElementById('countdown');
            
            const countdownInterval = setInterval(() => {
                countdown--;
                countdownElement.textContent = countdown;
                
                if (countdown <= 0) {
                    clearInterval(countdownInterval);
                }
            }, 1000);
            
            // Redirect setelah 7 detik (7000 milidetik)
            setTimeout(() => {
                window.location.href = "login.php";
            }, 7000);
            
            // Add hover effect to button
            const loginBtn = document.querySelector('.btn-login');
            if (loginBtn) {
                loginBtn.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-3px)';
                    this.style.boxShadow = 
                        '0 12px 30px rgba(79, 70, 229, 0.6),' +
                        '0 8px 20px rgba(124, 58, 237, 0.5),' +
                        '0 0 20px rgba(79, 70, 229, 0.4),' +
                        '0 0 40px rgba(124, 58, 237, 0.2),' +
                        'inset 0 1px 0 rgba(255, 255, 255, 0.3)';
                });
                
                loginBtn.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0)';
                    this.style.boxShadow = 
                        '0 6px 20px rgba(79, 70, 229, 0.4),' +
                        '0 3px 12px rgba(124, 58, 237, 0.3),' +
                        'inset 0 1px 0 rgba(255, 255, 255, 0.2)';
                });
                
                // Add ripple effect to button click
                loginBtn.addEventListener('click', function(e) {
                    const rect = this.getBoundingClientRect();
                    const x = e.clientX - rect.left;
                    const y = e.clientY - rect.top;
                    
                    const ripple = document.createElement('span');
                    ripple.style.cssText = `
                        position: absolute;
                        border-radius: 50%;
                        background: rgba(255, 255, 255, 0.4);
                        transform: scale(0);
                        animation: ripple 0.6s linear;
                        pointer-events: none;
                        width: 100px;
                        height: 100px;
                        left: ${x - 50}px;
                        top: ${y - 50}px;
                    `;
                    
                    this.appendChild(ripple);
                    
                    setTimeout(() => {
                        ripple.remove();
                    }, 600);
                });
                
                // Add ripple animation style
                const style = document.createElement('style');
                style.textContent = `
                    @keyframes ripple {
                        to {
                            transform: scale(4);
                            opacity: 0;
                        }
                    }
                    
                    @keyframes fadeIn {
                        from { opacity: 0; }
                        to { opacity: 1; }
                    }
                `;
                document.head.appendChild(style);
            }
            
            // Add logo hover effect
            const logoImage = document.querySelector('.logo-image');
            if (logoImage) {
                logoImage.addEventListener('mouseenter', function() {
                    this.style.animation = 'none';
                    this.style.transform = 'scale(1.15) rotate(10deg)';
                    setTimeout(() => {
                        this.style.animation = 'logoFloat 6s ease-in-out infinite';
                    }, 300);
                });
                
                logoImage.addEventListener('mouseleave', function() {
                    this.style.transform = 'scale(1) rotate(0deg)';
                });
            }
            
            // Add pulse animation to logout icon
            const icon = document.querySelector('.logout-icon i');
            if (icon) {
                setInterval(() => {
                    const randomGlow = 0.3 + Math.random() * 0.4;
                    icon.style.textShadow = 
                        `0 0 20px rgba(79, 70, 229, ${randomGlow}),
                         0 0 40px rgba(124, 58, 237, ${randomGlow * 0.5})`;
                }, 3000);
            }
            
            // Prevent form resubmission jika user klik back
            if (window.history.replaceState) {
                window.history.replaceState(null, null, window.location.href);
            }
            
            // Add container hover effect
            const container = document.querySelector('.logout-container');
            if (container) {
                container.addEventListener('mouseenter', function() {
                    this.style.boxShadow = 
                        '0 25px 50px rgba(79, 70, 229, 0.2),' +
                        '0 15px 35px rgba(124, 58, 237, 0.15),' +
                        '0 8px 20px rgba(79, 70, 229, 0.1),' +
                        '0 0 30px rgba(79, 70, 229, 0.3)';
                    this.style.transform = 'translateY(-5px)';
                });
                
                container.addEventListener('mouseleave', function() {
                    this.style.boxShadow = 
                        '0 20px 40px rgba(79, 70, 229, 0.15),' +
                        '0 10px 25px rgba(124, 58, 237, 0.1),' +
                        '0 5px 15px rgba(79, 70, 229, 0.05),' +
                        '0 0 20px rgba(79, 70, 229, 0.4),' +
                        '0 0 40px rgba(124, 58, 237, 0.2),' +
                        '0 0 60px rgba(79, 70, 229, 0.1)';
                    this.style.transform = 'translateY(0)';
                });
            }
        });
    </script>
</body>
</html>