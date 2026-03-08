/**
 * Live Photo Manager
 * Maneja múltiples Live Photos en la página
 */
class LivePhotoManager {
    constructor() {
        this.livePhotos = [];
        this.init();
    }

    init() {
        // Buscar todos los contenedores con la clase 'live-photo'
        const containers = document.querySelectorAll('.contenedor-imagen.live-photo');

        containers.forEach((container, index) => {
            // Crear una instancia para cada Live Photo
            const livePhoto = new LivePhoto(container, index);
            this.livePhotos.push(livePhoto);
        });

        console.log(`Inicializadas ${this.livePhotos.length} Live Photos`);
    }

    // Método para pausar todos los videos (útil para cambiar de página)
    pauseAll() {
        this.livePhotos.forEach(photo => photo.stop());
    }

    // Método para reiniciar todos los videos
    resetAll() {
        this.livePhotos.forEach(photo => photo.reset());
    }
}

/**
 * Clase Live Photo individual
 */
class LivePhoto {
    constructor(container, id) {
        this.container = container;
        this.id = id || Math.random().toString(36).substr(2, 9);

        // Encontrar los elementos dentro de este contenedor
        this.image = container.querySelector('.foto-estatica');
        this.video = container.querySelector('.live-video');
        this.badge = container.querySelector('.live-badge');

        this.isPlaying = false;
        this.isHovering = false;
        this.videoReady = false;

        this.init();
    }

    init() {
        if (!this.video || !this.image) {
            console.warn('Live Photo: Faltan elementos requeridos', this.container);
            return;
        }

        // Configurar el video
        this.video.setAttribute('preload', 'metadata');

        // Estado de carga
        this.container.classList.add('loading');

        // Esperar a que el video esté listo
        this.video.addEventListener('loadeddata', () => {
            this.videoReady = true;
            this.container.classList.remove('loading');
        });

        // Manejar errores de video
        this.video.addEventListener('error', (e) => {
            console.warn('Error cargando video:', e);
            this.container.classList.remove('loading');
            this.badge.style.opacity = '0.5';
        });

        // Precargar el video (sin reproducir)
        this.video.load();

        // Event listeners
        this.container.addEventListener('mouseenter', () => this.onMouseEnter());
        this.container.addEventListener('mouseleave', () => this.onMouseLeave());
        this.container.addEventListener('touchstart', (e) => this.onTouchStart(e));
        this.container.addEventListener('touchend', (e) => this.onTouchEnd(e));

        // Prevenir que el video capture eventos
        this.video.addEventListener('click', (e) => e.stopPropagation());
        this.video.addEventListener('mouseenter', (e) => e.stopPropagation());

        // Para dispositivos que prefieren reducir movimiento
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            this.container.classList.add('reduced-motion');
        }
    }

    onMouseEnter() {
        this.isHovering = true;
        this.play();
    }

    onMouseLeave() {
        this.isHovering = false;
        this.stop();
    }

    onTouchStart(e) {
        e.preventDefault();

        if (!this.isPlaying) {
            // Al tocar, reproducimos
            this.play();

            // Auto-detener después de un ciclo (opcional)
            if (this.video.loop) {
                this.video.loop = false;
                this.video.addEventListener('ended', () => {
                    this.stop();
                    this.video.loop = true; // Restaurar loop
                }, { once: true });
            }
        } else {
            // Si ya está reproduciendo, detenemos
            this.stop();
        }
    }

    onTouchEnd(e) {
        e.preventDefault();
        // Podemos añadir lógica extra si es necesario
    }

    async play() {
        if (this.isPlaying || !this.videoReady) return;

        try {
            // Asegurar que el video empieza desde el principio
            this.video.currentTime = 0;

            // Intentar reproducir
            await this.video.play();
            this.isPlaying = true;

            // Añadir clase para estilos adicionales
            this.container.classList.add('playing');

        } catch (error) {
            console.log('No se pudo reproducir el video:', error);

            // Fallback: si el error es por interacción, guardamos el intento
            if (error.name === 'NotAllowedError') {
                // Esperar a interacción del usuario
                const playOnInteraction = () => {
                    this.video.play()
                        .then(() => {
                            this.isPlaying = true;
                            this.container.classList.add('playing');
                        })
                        .catch(e => console.log('Error en fallback:', e));

                    document.removeEventListener('click', playOnInteraction);
                    document.removeEventListener('touchstart', playOnInteraction);
                };

                document.addEventListener('click', playOnInteraction, { once: true });
                document.addEventListener('touchstart', playOnInteraction, { once: true });
            }
        }
    }

    stop() {
        if (!this.isPlaying) return;

        this.video.pause();
        this.video.currentTime = 0;
        this.isPlaying = false;
        this.container.classList.remove('playing');
    }

    reset() {
        this.stop();
        this.video.currentTime = 0;
    }

    // Método para destruir la instancia (limpieza)
    destroy() {
        this.stop();
        this.video = null;
        this.image = null;
        this.badge = null;
        this.container = null;
    }
}

/**
 * Inicialización cuando el DOM está listo
 */
document.addEventListener('DOMContentLoaded', () => {
    // Crear el manager global
    window.livePhotoManager = new LivePhotoManager();
});

/**
 * Opcional: Mejorar la experiencia en dispositivos móviles
 */
function setupMobileOptimizations() {
    const isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);

    if (isMobile) {
        // En móvil, cambiar el comportamiento para conservar batería
        document.querySelectorAll('.live-video').forEach(video => {
            video.setAttribute('preload', 'none');
        });

        // Reducir calidad de video en móvil (opcional)
        document.querySelectorAll('source').forEach(source => {
            if (source.src.includes('.mp4')) {
                // Podrías cambiar a una versión de menor calidad
                // source.src = source.src.replace('.mp4', '-mobile.mp4');
            }
        });
    }
}

// Llamar a la optimización móvil
setupMobileOptimizations();

/**
 * Intersection Observer para pausar videos fuera de la vista
 * (Ahorra batería y recursos)
 */
function setupIntersectionObserver() {
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            const container = entry.target;
            const livePhoto = window.livePhotoManager?.livePhotos.find(
                lp => lp.container === container
            );

            if (!entry.isIntersecting && livePhoto?.isPlaying) {
                // Si el elemento no es visible y está reproduciendo, lo pausamos
                livePhoto.stop();
            }
        });
    }, {
        threshold: 0.1,
        rootMargin: '50px'
    });

    // Observar todos los contenedores
    document.querySelectorAll('.contenedor-imagen.live-photo').forEach(container => {
        observer.observe(container);
    });
}

// Configurar observer después de la inicialización
setTimeout(setupIntersectionObserver, 1000);

/**
 * Ejemplo de API pública para interactuar con las Live Photos
 */
window.LivePhotoAPI = {
    // Reproducir una Live Photo específica por índice
    playByIndex: (index) => {
        const photos = window.livePhotoManager?.livePhotos;
        if (photos && photos[index]) {
            photos[index].play();
        }
    },

    // Detener todas
    stopAll: () => {
        window.livePhotoManager?.pauseAll();
    },

    // Actualizar configuración
    updateConfig: (config) => {
        // Ejemplo: cambiar duración, loop, etc.
        document.querySelectorAll('.live-video').forEach(video => {
            if (config.loop !== undefined) video.loop = config.loop;
            if (config.muted !== undefined) video.muted = config.muted;
        });
    }
};