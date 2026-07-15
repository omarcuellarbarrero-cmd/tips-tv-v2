// ============================================
// CONFIGURACIÓN DE USUARIOS
// Cambia estos datos por los de tus colegas
// Formato: 'usuario': 'contraseña'
// ============================================
const USERS = {
    'admin': 'admin2026',
    'tecnico1': 'clave123',
    'tecnico2': 'clave456',
    'tecnico3': 'clave456',// ← ¿Cómo lo agregaste?
    'carlos': 'reparador789'    
};

// ============================================
// Si ya tiene sesión activa, ir directo a la app
// ============================================
if (sessionStorage.getItem('userLoggedIn') === 'true') {
    window.location.href = 'app.html';
}

// ============================================
// Manejar el formulario de ingreso
// ============================================
document.getElementById('loginForm').addEventListener('submit', function (e) {
    e.preventDefault();

    const username = document.getElementById('username').value.trim().toLowerCase();
    const password = document.getElementById('password').value;
    const errorMessage = document.getElementById('errorMessage');

    // Limpiar mensaje previo
    errorMessage.textContent = '';

    // Validar credenciales
    if (USERS[username] && USERS[username] === password) {
        // Login exitoso
        sessionStorage.setItem('userLoggedIn', 'true');
        sessionStorage.setItem('currentUser', username);
        window.location.href = 'app.html';
    } else {
        // Error
        errorMessage.textContent = '❌ Usuario o contraseña incorrectos. Intente de nuevo.';

        // Limpiar campos
        document.getElementById('password').value = '';
        document.getElementById('password').focus();
    }
});
