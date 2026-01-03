<?php
// includes/footer.php
?>
        </main>
        <footer>  
            <div class="container">
                <div class="footer-content">
                    <div class="footer-text">
                        <p class="footer-copyright">
                            &copy; <?= date('Y') ?> <strong class="highlight-text">Parapatan Tailor</strong>. All rights reserved.
                        </p>
                        <p class="footer-slogan">
                            <span class="typing-text">"Kualitas Jahitan Terbaik untuk Penampilan Anda."</span>
                        </p>
                    </div>
                    <div class="footer-social">
                        <a href="#" class="social-icon" data-tooltip="Facebook">
                            <i class="fab fa-facebook-f"></i>
                        </a>
                        <a href="#" class="social-icon" data-tooltip="Instagram">
                            <i class="fab fa-instagram"></i>
                        </a>
                        <a href="#" class="social-icon" data-tooltip="WhatsApp">
                            <i class="fab fa-whatsapp"></i>
                        </a>
                        <a href="#" class="social-icon" data-tooltip="Email">
                            <i class="fas fa-envelope"></i>
                        </a>
                    </div>
                </div>
            </div>
            <div class="footer-bottom">
                <div class="container">
                    <div class="scroll-top" id="scrollTop">
                        <i class="fas fa-arrow-up"></i>
                    </div>
                </div>
            </div>
        </footer>
    </div>
</div>

<!-- JavaScript Libraries -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/typed.js@2.0.12"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="../assets/js/script.js"></script>

<!-- Footer Animation Styles -->
<style>
    footer {
        position: relative;
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        padding: 30px 0 15px;
        margin-top: 50px;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        overflow: hidden;
        color: #333;
        border-top: 1px solid #dee2e6;
        font-size: 14px; /* Font size standar untuk footer */
    }

    .footer-content {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 18px;
        position: relative;
        z-index: 2;
    }

    .footer-text {
        text-align: center;
        opacity: 0;
        transform: translateY(20px);
        animation: fadeInUp 0.8s ease forwards;
        animation-delay: 0.5s;
    }

    .footer-copyright {
        font-size: 14px; /* Lebih kecil dari 16px */
        margin-bottom: 8px;
        font-weight: 500;
        color: #495057;
        line-height: 1.4;
    }

    .highlight-text {
        color: #4f46e5;
        position: relative;
        display: inline-block;
        font-weight: 600;
    }

    .highlight-text::after {
        content: '';
        position: absolute;
        bottom: -2px;
        left: 0;
        width: 100%;
        height: 2px;
        background: linear-gradient(90deg, #4f46e5, #7c3aed, #a855f7);
        transform: scaleX(0);
        transform-origin: right;
        transition: transform 0.3s ease;
    }

    .highlight-text:hover::after {
        transform: scaleX(1);
        transform-origin: left;
    }

    .footer-slogan {
        font-size: 13px; /* Lebih kecil dari 14px */
        color: #6c757d;
        font-style: italic;
        margin-bottom: 0;
        line-height: 1.4;
    }

    .typing-text {
        display: inline-block;
        min-height: 18px;
        color: #4f46e5;
        font-weight: 500;
    }

    .footer-social {
        display: flex;
        gap: 12px;
        margin-top: 12px;
        opacity: 0;
        transform: translateY(20px);
        animation: fadeInUp 0.8s ease forwards;
        animation-delay: 0.7s;
    }

    .social-icon {
        width: 34px;
        height: 34px;
        border-radius: 50%;
        background: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #4f46e5;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        transition: all 0.3s ease;
        position: relative;
        text-decoration: none;
        font-size: 13px; /* Lebih kecil dari 14px */
    }

    .social-icon:hover {
        transform: translateY(-2px);
        background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
        color: #fff;
        box-shadow: 0 4px 12px rgba(79, 70, 229, 0.3);
    }

    .social-icon::after {
        content: attr(data-tooltip);
        position: absolute;
        bottom: -32px;
        left: 50%;
        transform: translateX(-50%);
        background: #374151;
        color: #fff;
        padding: 3px 6px;
        border-radius: 3px;
        font-size: 10px; /* Lebih kecil dari 11px */
        opacity: 0;
        visibility: hidden;
        transition: all 0.3s ease;
        white-space: nowrap;
        z-index: 10;
    }

    .social-icon:hover::after {
        opacity: 1;
        visibility: visible;
        bottom: -28px;
    }

    .footer-bottom {
        margin-top: 25px;
        padding-top: 18px;
        border-top: 1px solid rgba(79, 70, 229, 0.2);
        position: relative;
    }

    .scroll-top {
        position: absolute;
        right: 0;
        top: -42px;
        width: 36px;
        height: 36px;
        background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
        color: #fff;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        box-shadow: 0 2px 8px rgba(79, 70, 229, 0.3);
        opacity: 0;
        visibility: hidden;
        transition: all 0.3s ease;
        font-size: 13px; /* Lebih kecil dari 14px */
    }

    .scroll-top.active {
        opacity: 1;
        visibility: visible;
        top: -32px;
    }

    .scroll-top:hover {
        background: linear-gradient(135deg, #7c3aed 0%, #a855f7 100%);
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(124, 58, 237, 0.4);
    }

    @keyframes fadeInUp {
        0% { opacity: 0; transform: translateY(20px); }
        100% { opacity: 1; transform: translateY(0); }
    }

    /* Responsive Design */
    @media (max-width: 768px) {
        footer {
            padding: 25px 0 12px;
            margin-top: 35px;
            font-size: 13px; /* Font size lebih kecil untuk mobile */
        }

        .footer-content {
            gap: 15px;
        }

        .footer-copyright {
            font-size: 13px; /* Lebih kecil untuk mobile */
        }

        .footer-slogan {
            font-size: 12px; /* Lebih kecil untuk mobile */
        }

        .footer-social {
            gap: 10px;
        }

        .social-icon {
            width: 30px;
            height: 30px;
            font-size: 12px; /* Lebih kecil untuk mobile */
        }

        .social-icon::after {
            font-size: 9px; /* Lebih kecil untuk mobile */
            bottom: -30px;
        }

        .social-icon:hover::after {
            bottom: -26px;
        }

        .scroll-top {
            width: 32px;
            height: 32px;
            top: -38px;
            font-size: 12px; /* Lebih kecil untuk mobile */
        }

        .scroll-top.active {
            top: -28px;
        }

        .footer-bottom {
            margin-top: 20px;
            padding-top: 15px;
        }
    }

    @media (max-width: 480px) {
        footer {
            padding: 20px 0 10px;
            margin-top: 30px;
            font-size: 12px; /* Font size lebih kecil untuk mobile kecil */
        }

        .footer-text {
            text-align: center;
        }

        .footer-copyright {
            font-size: 12px; /* Lebih kecil untuk mobile kecil */
            margin-bottom: 6px;
        }

        .footer-slogan {
            font-size: 11px; /* Lebih kecil untuk mobile kecil */
        }

        .footer-social {
            gap: 8px;
            margin-top: 10px;
        }

        .social-icon {
            width: 28px;
            height: 28px;
            font-size: 11px; /* Lebih kecil untuk mobile kecil */
        }

        .scroll-top {
            width: 30px;
            height: 30px;
            top: -35px;
            font-size: 11px; /* Lebih kecil untuk mobile kecil */
        }

        .scroll-top.active {
            top: -25px;
        }
    }

    /* Animation improvements for better performance */
    .footer-text, .footer-social {
        will-change: transform, opacity;
    }

    /* Smooth transitions for all interactive elements */
    .social-icon, .scroll-top, .highlight-text {
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }
</style>

<script>
    // Typing animation for slogan - Tailor specific
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize Typed.js for tailor slogans
        if(document.querySelector('.typing-text')) {
            var typed = new Typed('.typing-text', {
                strings: [
                    '"Kualitas Jahitan Terbaik untuk Penampilan Anda."',
                    '"Custom Tailor dengan Presisi Tinggi."',
                    '"Solusi Fashion Terbaik Keluarga Anda."',
                    '"Jahitan Berkualitas, Harga Terjangkau."',
                    '"Mewujudkan Gaya Impian Anda."',
                    '"Profesional dalam Setiap Detail Jahitan."'
                ],
                typeSpeed: 40,
                backSpeed: 25,
                loop: true,
                showCursor: false,
                backDelay: 2000,
                startDelay: 500
            });
        }

        // Scroll to top button functionality
        const scrollTop = document.getElementById('scrollTop');
        
        window.addEventListener('scroll', function() {
            if (window.scrollY > 300) {
                scrollTop.classList.add('active');
            } else {
                scrollTop.classList.remove('active');
            }
        });

        scrollTop.addEventListener('click', function() {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });

        // Social icons animation
        const socialIcons = document.querySelectorAll('.social-icon');
        
        socialIcons.forEach(icon => {
            icon.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-2px) scale(1.05)';
            });
            
            icon.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0) scale(1)';
            });
        });

        // Add loading animation to footer
        const footer = document.querySelector('footer');
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    footer.style.animation = 'fadeInUp 0.8s ease forwards';
                }
            });
        });

        observer.observe(footer);
    });
</script>

</body>
</html>