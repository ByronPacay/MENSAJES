// Clave unica para recordar la preferencia visual del usuario.
const THEME_STORAGE_KEY = "mensajeria_theme";

// Aplicar el tema elegido y sincronizar el texto de los botones.
function applyTheme(theme) {
    document.body.dataset.theme = theme === "light" ? "light" : "dark";
    updateThemeButtons();
}

// Cambiar la etiqueta visible de cada boton de tema.
function updateThemeButtons() {
    const currentTheme = document.body.dataset.theme === "light" ? "light" : "dark";
    const nextLabel = currentTheme === "light" ? "Modo oscuro" : "Modo claro";

    document.querySelectorAll("[data-theme-toggle]").forEach((button) => {
        button.textContent = nextLabel;
    });
}

// Leer la preferencia guardada previamente o usar el modo oscuro por defecto.
function getStoredTheme() {
    return localStorage.getItem(THEME_STORAGE_KEY) || "dark";
}

// Alternar entre el tema oscuro y el claro desde cualquier vista.
function toggleTheme() {
    const nextTheme = document.body.dataset.theme === "light" ? "dark" : "light";
    localStorage.setItem(THEME_STORAGE_KEY, nextTheme);
    applyTheme(nextTheme);
}

// Preparar el tema inicial apenas el documento este listo.
document.addEventListener("DOMContentLoaded", () => {
    applyTheme(getStoredTheme());

    document.querySelectorAll("[data-theme-toggle]").forEach((button) => {
        button.addEventListener("click", toggleTheme);
    });
});
