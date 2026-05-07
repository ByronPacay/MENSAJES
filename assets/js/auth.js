// Bloque principal para controlar el cambio entre login y registro.
const tabButtons = document.querySelectorAll(".tab-button");
const authForms = document.querySelectorAll(".auth-form");
const loginForm = document.getElementById("loginForm");
const registerForm = document.getElementById("registerForm");

// Cambiar de vista segun la pestana pulsada por el usuario.
tabButtons.forEach((button) => {
    button.addEventListener("click", () => {
        const target = button.dataset.viewTarget;

        tabButtons.forEach((item) => item.classList.remove("active"));
        authForms.forEach((form) => form.classList.remove("active"));

        button.classList.add("active");
        document.getElementById(`${target}Form`).classList.add("active");
    });
});

// Enviar formularios al backend con manejo uniforme de errores.
async function sendForm(endpoint, formData) {
    const response = await fetch(endpoint, {
        method: "POST",
        body: formData,
    });

    const rawText = await response.text();
    let data = null;

    try {
        data = JSON.parse(rawText);
    } catch (error) {
        throw new Error(
            "El servidor no devolvio un JSON valido. Revisa la conexion a la base de datos, que exista bd_mensajeria y que sus tablas esten importadas."
        );
    }

    if (!response.ok || !data.ok) {
        throw new Error(data.message || "Ocurrio un error inesperado.");
    }

    return data;
}

// Procesar el inicio de sesion y redirigir al panel del chat.
loginForm.addEventListener("submit", async (event) => {
    event.preventDefault();

    const formData = new FormData(loginForm);

    try {
        const data = await sendForm("api/login.php", formData);

        await Swal.fire({
            icon: "success",
            title: "Sesion iniciada",
            text: data.message,
            confirmButtonText: "Continuar",
        });

        window.location.href = "chat.php";
    } catch (error) {
        Swal.fire({
            icon: "error",
            title: "No fue posible iniciar sesion",
            text: error.message,
            confirmButtonText: "Entendido",
        });
    }
});

// Procesar el registro y llevar al usuario de vuelta al formulario de acceso.
registerForm.addEventListener("submit", async (event) => {
    event.preventDefault();

    const formData = new FormData(registerForm);

    try {
        const data = await sendForm("api/register.php", formData);

        await Swal.fire({
            icon: "success",
            title: "Cuenta creada",
            text: data.message,
            confirmButtonText: "Ir al inicio de sesion",
        });

        registerForm.reset();
        document.querySelector('[data-view-target="login"]').click();
    } catch (error) {
        Swal.fire({
            icon: "error",
            title: "No fue posible crear la cuenta",
            text: error.message,
            confirmButtonText: "Revisar datos",
        });
    }
});
