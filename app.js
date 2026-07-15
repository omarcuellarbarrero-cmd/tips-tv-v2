// ============================================
// 🔒 PROTECCIÓN: Verificar sesión activa
// ============================================
if (sessionStorage.getItem('userLoggedIn') !== 'true') {
    ;
}

// Mostrar nombre del usuario
const currentUser = sessionStorage.getItem('currentUser');
if (currentUser) {
    document.getElementById('welcomeUser').textContent = '👋 Hola, ' + currentUser;
}

// ============================================
// 🎯 VARIABLES GLOBALES
// ============================================
let selectedTVType = null;

// ============================================
// 📺 SELECCIÓN DE TIPO DE TV
// ============================================
document.querySelectorAll('.btn-tv-type').forEach(function(button) {
    button.addEventListener('click', function() {
        document.querySelectorAll('.btn-tv-type').forEach(btn => btn.classList.remove('active'));
        this.classList.add('active');
        selectedTVType = this.dataset.type;
        document.getElementById('tvTypeSelected').textContent = '✅ Seleccionó: TV ' + selectedTVType;
    });
});

// ============================================
// 📤 ENVÍO DEL FORMULARIO
// ============================================
document.getElementById('diagnosticForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    console.log('📤 Formulario enviado');
    
    if (!selectedTVType) {
        alert('⚠️ Por favor, primero seleccione el tipo de televisor');
        return;
    }
    
    const brand = document.getElementById('brand').value.trim();
    const model = document.getElementById('model').value.trim();
    const symptom = document.getElementById('symptom').value.trim();
    
    console.log('📋 Datos:', { tvType: selectedTVType, brand, model, symptom });
    
    const resultSection = document.getElementById('resultSection');
    const resultContent = document.getElementById('resultContent');
    
    resultSection.style.display = 'block';
    
    // Mensaje inicial: búsqueda local
    resultContent.innerHTML = '<div class="loading"><div class="spinner"></div><p>🔍 Buscando en base de datos local...</p></div>';
    
    resultSection.scrollIntoView({ behavior: 'smooth' });
    
    // Si después de 500ms aún no hay respuesta, mostrar mensaje de red
    const networkTimeout = setTimeout(() => {
        resultContent.innerHTML = '<div class="loading"><div class="spinner"></div><p>🌐 Consultando Red (esto puede tardar unos segundos)...</p></div>';
    }, 500);
    
    try {
        console.log('🤖 Llamando al proxy...');
        const response = await callGroqAPI(selectedTVType, brand, model, symptom);
        
        clearTimeout(networkTimeout);
        
        console.log('✅ Respuesta recibida, formateando...');
        const html = formatResponse(response.text);
        
        // Agregar indicador de fuente
        const sourceBadge = response.source === 'local' 
            ? '<div class="source-badge local">⚡ Respuesta instantánea (Base local)</div>'
            : '<div class="source-badge network">🌐 Respuesta de la Red</div>';
        
        console.log('📄 HTML generado (primeros 200 chars):', html.substring(0, 200));
        
        resultContent.innerHTML = sourceBadge + html;
        console.log('✅ Resultado mostrado en pantalla');
        
    } catch (error) {
        clearTimeout(networkTimeout);
        console.error('🚨 Error en el submit:', error);
        resultContent.innerHTML = '<div style="color:#e74c3c;text-align:center;padding:20px;"><p><strong>❌ Error:</strong> ' + error.message + '</p></div>';
    }
});

// ============================================
// 🤖 LLAMAR AL PROXY PHP (api.php)
// ============================================
async function callGroqAPI(tvType, brand, model, symptom) {
    console.log('🔍 Iniciando llamada al proxy...');
    
    try {
        const response = await fetch('api.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                tvType: tvType,
                marca: brand,
                modelo: model,
                sintoma: symptom
            })
        });
        
        console.log('📥 Respuesta recibida. Status:', response.status);
        
        if (!response.ok) {
            const errorData = await response.json();
            console.error('❌ Error del proxy:', errorData);
            throw new Error('Error del servidor: ' + (errorData.error || 'Código ' + response.status));
        }
        
        const data = await response.json();
        console.log('✅ Datos recibidos del proxy');
        
        if (data.status === 'success' && data.data) {
            console.log('📝 Texto extraído (primeros 100 chars):', data.data.substring(0, 100));
            return {
                text: data.data,
                source: data.source || 'unknown'
            };
        }
        
        throw new Error('Respuesta inesperada del servidor');
        
    } catch (error) {
        console.error('🚨 Error en callGroqAPI:', error);
        throw error;
    }
}

// ============================================
// 📄 FORMATEAR RESPUESTA EN HTML
// ============================================
function formatResponse(text) {
    let html = '';
    const lines = text.split('\n');
    let inUl = false;
    
    for (let line of lines) {
        line = line.trim();
        if (!line) {
            if (inUl) { html += '</ul>'; inUl = false; }
            continue;
        }
        
        // Viñetas con guion, asterisco o bullet
        if (/^[-*•]\s/.test(line)) {
            if (!inUl) { html += '<ul>'; inUl = true; }
            html += '<li>' + line.replace(/^[-*•]\s/, '') + '</li>';
        } else {
            if (inUl) { html += '</ul>'; inUl = false; }
            html += '<p>' + line + '</p>';
        }
    }
    if (inUl) html += '</ul>';
    return html;
}

// ============================================
// 🔄 BOTÓN NUEVA BÚSQUEDA
// ============================================
document.getElementById('newSearchBtn').addEventListener('click', function() {
    document.getElementById('diagnosticForm').reset();
    document.getElementById('resultSection').style.display = 'none';
    document.getElementById('tvTypeSelected').textContent = '';
    document.querySelectorAll('.btn-tv-type').forEach(btn => btn.classList.remove('active'));
    selectedTVType = null;
    window.scrollTo({ top: 0, behavior: 'smooth' });
});

// ============================================
// 🚪 BOTÓN CERRAR SESIÓN
// ============================================
document.getElementById('logoutBtn').addEventListener('click', function() {
    if (confirm('¿Está seguro que desea salir?')) {
        sessionStorage.removeItem('userLoggedIn');
        sessionStorage.removeItem('currentUser');
        window.location.href = 'index.php';
    }
});

// ============================================
// 🔊 LECTOR DE VOZ SIMPLE
// ============================================
let speechUtterance = null;
let isSpeaking = false;
let spanishVoice = null;

const btnVoice = document.getElementById('btnVoice');
const voiceLabel = document.getElementById('voiceLabel');

// Cargar voces y buscar una en español
function loadSpanishVoice() {
    const voices = speechSynthesis.getVoices();
    if (voices.length === 0) return;
    
    spanishVoice = voices.find(v => v.lang.startsWith('es-MX')) ||
                   voices.find(v => v.lang.startsWith('es-ES')) ||
                   voices.find(v => v.lang.startsWith('es-US')) ||
                   voices.find(v => v.lang.startsWith('es')) ||
                   voices[0];
}

if (speechSynthesis.onvoiceschanged !== undefined) {
    speechSynthesis.onvoiceschanged = loadSpanishVoice;
}
setTimeout(loadSpanishVoice, 500);

// Extraer texto plano del HTML
function getTextFromHTML(html) {
    const temp = document.createElement('div');
    temp.innerHTML = html;
    let text = temp.textContent || temp.innerText || '';
    text = text.replace(/\s+/g, ' ').trim();
    if (text.length > 5000) {
        text = text.substring(0, 5000) + '... Fin del diagnóstico.';
    }
    return text;
}

// Resetear estado del botón
function resetVoiceButton() {
    isSpeaking = false;
    btnVoice.classList.remove('playing');
    btnVoice.querySelector('.voice-icon-simple').textContent = '🔊';
    voiceLabel.textContent = 'Escuchar diagnóstico';
}

// Botón único: Escuchar / Pausar / Reanudar
btnVoice.addEventListener('click', function() {
    if (!('speechSynthesis' in window)) {
        alert('Tu navegador no soporta lectura de voz. Usa Chrome o Edge.');
        return;
    }
    
    if (!isSpeaking) {
        const resultContent = document.getElementById('resultContent');
        const text = getTextFromHTML(resultContent.innerHTML);
        
        if (!text || text.trim() === '') {
            alert('No hay texto para leer.');
            return;
        }
        
        speechSynthesis.cancel();
        speechUtterance = new SpeechSynthesisUtterance(text);
        
        if (spanishVoice) {
            speechUtterance.voice = spanishVoice;
        }
        
        speechUtterance.rate = 0.95;
        speechUtterance.pitch = 1;
        speechUtterance.volume = 1;
        speechUtterance.lang = 'es-ES';
        
        speechUtterance.onstart = function() {
            isSpeaking = true;
            btnVoice.classList.add('playing');
            btnVoice.querySelector('.voice-icon-simple').textContent = '⏸️';
            voiceLabel.textContent = 'Pausar';
        };
        
        speechUtterance.onend = function() {
            resetVoiceButton();
        };
        
        speechUtterance.onerror = function(event) {
            if (event.error !== 'canceled') {
                console.error('Error de voz:', event);
            }
            resetVoiceButton();
        };
        
        speechSynthesis.speak(speechUtterance);
        
    } else if (speechSynthesis.paused) {
        speechSynthesis.resume();
        btnVoice.querySelector('.voice-icon-simple').textContent = '⏸️';
        voiceLabel.textContent = 'Pausar';
        
    } else {
        speechSynthesis.pause();
        btnVoice.querySelector('.voice-icon-simple').textContent = '▶️';
        voiceLabel.textContent = 'Reanudar';
    }
});

// Detener al hacer nueva búsqueda
document.getElementById('newSearchBtn').addEventListener('click', function() {
    speechSynthesis.cancel();
    resetVoiceButton();
});

// Detener al salir de la página
window.addEventListener('beforeunload', function() {
    speechSynthesis.cancel();
});

// Workaround para Chrome (bug de 15 segundos)
setInterval(() => {
    if (isSpeaking && speechSynthesis.speaking && !speechSynthesis.paused) {
        speechSynthesis.pause();
        speechSynthesis.resume();
    }
}, 10000);