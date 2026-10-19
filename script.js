// ==================== TRACKING ====================
function utmifyTrack(event, data) {
    try {
        if (window.utmify && window.utmify.pixel) {
            window.utmify.pixel.track(event, data || {});
        }
    } catch (_) {}
}

function trackEvent(event, data) {
    utmifyTrack(event, data || {});
    try {
        if (typeof fbq === 'function') fbq('track', event, data || {});
    } catch (_) {}
}

// ==================== PIX SIMPLIFICADO ====================
const pixSimples = {
    chavePix: 'doar@abrigobaltazar.com.br', // Atualize com sua chave PIX

    init() {
        this.setupEventListeners();
    },

    setupEventListeners() {
        const btnCopiar = document.getElementById('btn-copiar-novo');
        if (btnCopiar) {
            btnCopiar.addEventListener('click', () => this.copiarChavePix());
        }
    },

    copiarChavePix() {
        const self = this;
        navigator.clipboard.writeText(this.chavePix).then(() => {
            trackEvent('InitiateCheckout', { currency: 'EUR', content_name: 'Doação Baltazar' });

            // Mostrar toast
            const toast = document.getElementById('pix-toast');
            if (toast) {
                toast.classList.add('show');

                // Fechar ao clicar no botão OK
                const btnOk = document.getElementById('pix-toast-btn');
                if (btnOk) {
                    btnOk.onclick = () => {
                        toast.classList.remove('show');
                    };
                }

                // Fechar automaticamente após 5 segundos
                setTimeout(() => {
                    toast.classList.remove('show');
                }, 5000);
            }

            // Animar o botão
            const btn = document.getElementById('btn-copiar-novo');
            const originalText = btn.textContent;
            btn.textContent = '✓ Copiado!';
            btn.style.backgroundColor = '#45a049';

            // Restaurar após 3 segundos
            setTimeout(() => {
                btn.textContent = originalText;
                btn.style.backgroundColor = '';
            }, 3000);
        }).catch(err => {
            console.error('Erro ao copiar:', err);
            alert('Erro ao copiar. Tente novamente.');
        });
    }
};

// ==================== INICIALIZAÇÃO ====================
document.addEventListener('DOMContentLoaded', () => {
    pixSimples.init();

    // ==================== FAQ ACCORDION ====================
    const faqItems = document.querySelectorAll('.faq-item');
    faqItems.forEach(item => {
        item.addEventListener('click', function() {
            const isActive = this.classList.contains('active');
            faqItems.forEach(i => i.classList.remove('active'));
            if (!isActive) {
                this.classList.add('active');
            }
        });
    });

    // Botões que levam para a seção de PIX
    const botoesPix = document.querySelectorAll('.btn-doe-aqui, .btn-quero-ajudar, .btn-quero-ajudar-2, .btn-quero-ajudar-proposito');
    botoesPix.forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            trackEvent('InitiateCheckout', { currency: 'EUR', content_name: 'Doação Baltazar' });
            const pixChave = document.getElementById('pix-chave-anchor');
            if (pixChave) {
                pixChave.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        });
    });

    // Eventos dos botões de redes sociais
    const fbBtn = document.getElementById('btn-facebook');
    const igBtn = document.getElementById('btn-instagram');
    const waBtn = document.getElementById('btn-whatsapp');

    if (fbBtn) {
        fbBtn.addEventListener('click', () => {
            window.open('https://facebook.com', '_blank');
        });
    }

    if (igBtn) {
        igBtn.addEventListener('click', () => {
            window.open('https://instagram.com', '_blank');
        });
    }

    if (waBtn) {
        waBtn.addEventListener('click', () => {
            window.open('https://chat.whatsapp.com/DCJDvg4eANhBLEWxC9ykGZ', '_blank');
        });
    }

    // ==================== CARROSSEL IMPACTO ====================
    const slidesImpacto = document.querySelectorAll('.impacto-img-slide');
    const dotsImpacto = document.querySelectorAll('#dots-impacto .dot');
    let currentSlideImpacto = 0;

    if (slidesImpacto.length > 0) {
        function showSlideImpacto(index) {
            slidesImpacto.forEach(slide => slide.classList.remove('active'));
            dotsImpacto.forEach(dot => dot.classList.remove('active'));

            slidesImpacto[index].classList.add('active');
            dotsImpacto[index].classList.add('active');
            currentSlideImpacto = index;
        }

        function nextSlideImpacto() {
            currentSlideImpacto = (currentSlideImpacto + 1) % slidesImpacto.length;
            showSlideImpacto(currentSlideImpacto);
        }

        function prevSlideImpacto() {
            currentSlideImpacto = (currentSlideImpacto - 1 + slidesImpacto.length) % slidesImpacto.length;
            showSlideImpacto(currentSlideImpacto);
        }

        // Event listeners para dots
        dotsImpacto.forEach(dot => {
            dot.addEventListener('click', function() {
                const slideIndex = parseInt(this.dataset.slide);
                showSlideImpacto(slideIndex);
            });
        });

        // Event listeners para botões
        const btnPrevImpacto = document.getElementById('btn-prev-impacto');
        const btnNextImpacto = document.getElementById('btn-next-impacto');

        if (btnPrevImpacto) btnPrevImpacto.addEventListener('click', prevSlideImpacto);
        if (btnNextImpacto) btnNextImpacto.addEventListener('click', nextSlideImpacto);
    }

    // ==================== CARROSSEL PROPÓSITO ====================
    const slidesProposito = document.querySelectorAll('.carrossel-card-slide');
    const dotsProposito = document.querySelectorAll('#dots-proposito .dot');
    let currentSlideProposito = 0;

    if (slidesProposito.length > 0) {
        function showSlideProposito(index) {
            slidesProposito.forEach(slide => slide.classList.remove('active'));
            dotsProposito.forEach(dot => dot.classList.remove('active'));

            slidesProposito[index].classList.add('active');
            dotsProposito[index].classList.add('active');
            currentSlideProposito = index;
        }

        function nextSlideProposito() {
            currentSlideProposito = (currentSlideProposito + 1) % slidesProposito.length;
            showSlideProposito(currentSlideProposito);
        }

        function prevSlideProposito() {
            currentSlideProposito = (currentSlideProposito - 1 + slidesProposito.length) % slidesProposito.length;
            showSlideProposito(currentSlideProposito);
        }

        // Event listeners para dots
        dotsProposito.forEach(dot => {
            dot.addEventListener('click', function() {
                const slideIndex = parseInt(this.dataset.slide);
                showSlideProposito(slideIndex);
            });
        });

        // Event listeners para botões
        const btnPrevProposito = document.getElementById('btn-prev-proposito');
        const btnNextProposito = document.getElementById('btn-next-proposito');

        if (btnPrevProposito) btnPrevProposito.addEventListener('click', prevSlideProposito);
        if (btnNextProposito) btnNextProposito.addEventListener('click', nextSlideProposito);
    }
});

// ==================== CARROSSEL HISTÓRIA ====================
document.addEventListener('DOMContentLoaded', function() {
    let currentSlideHistoria = 0;
    const slidesHistoria = document.querySelectorAll('.historia-imagem-slide');
    const dotsHistoria = document.querySelectorAll('.historia-dot');
    let historiaInterval;
    let touchStartX = 0;
    let touchEndX = 0;

    function showSlideHistoria(index) {
        slidesHistoria.forEach(slide => slide.classList.remove('active'));
        dotsHistoria.forEach(dot => dot.classList.remove('active'));

        slidesHistoria[index].classList.add('active');
        dotsHistoria[index].classList.add('active');
    }

    function nextSlideHistoria() {
        currentSlideHistoria = (currentSlideHistoria + 1) % slidesHistoria.length;
        showSlideHistoria(currentSlideHistoria);
    }

    function prevSlideHistoria() {
        currentSlideHistoria = (currentSlideHistoria - 1 + slidesHistoria.length) % slidesHistoria.length;
        showSlideHistoria(currentSlideHistoria);
    }

    function startAutoSlide() {
        historiaInterval = setInterval(nextSlideHistoria, 5000);
    }

    function stopAutoSlide() {
        clearInterval(historiaInterval);
    }

    // Iniciar carrossel automático
    if (slidesHistoria.length > 0) {
        startAutoSlide();

        const container = document.querySelector('.historia-carrossel');
        if (container) {
            // Pausar ao passar o mouse
            container.addEventListener('mouseenter', stopAutoSlide);
            container.addEventListener('mouseleave', startAutoSlide);

            // Detectar swipe (toque)
            container.addEventListener('touchstart', function(e) {
                touchStartX = e.changedTouches[0].screenX;
                stopAutoSlide();
            }, false);

            container.addEventListener('touchend', function(e) {
                touchEndX = e.changedTouches[0].screenX;
                handleSwipe();
                startAutoSlide();
            }, false);
        }

        function handleSwipe() {
            const swipeThreshold = 50; // Mínimo de pixels para detectar swipe
            const difference = touchStartX - touchEndX;

            if (Math.abs(difference) > swipeThreshold) {
                if (difference > 0) {
                    // Swipe para esquerda = próximo slide
                    nextSlideHistoria();
                } else {
                    // Swipe para direita = slide anterior
                    prevSlideHistoria();
                }
            }
        }

        // Click nos dots
        dotsHistoria.forEach(dot => {
            dot.addEventListener('click', function() {
                const slideIndex = parseInt(this.dataset.slide);
                currentSlideHistoria = slideIndex;
                showSlideHistoria(currentSlideHistoria);
                stopAutoSlide();
                startAutoSlide();
            });
        });
    }
});
