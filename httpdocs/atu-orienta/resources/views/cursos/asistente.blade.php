<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Asistente de IA · Grupo ATU</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link href="https://fonts.googleapis.com/css2?family=Source+Serif+4:ital,opsz,wght@0,8..60,200..900;1,8..60,200..900&family=Source+Sans+3:ital,wght@0,200..900;1,200..900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/atu.css') }}">
</head>

<body class="assistant-body">
<div class="assistant-app">

    <header class="assistant-header">
        <div class="assistant-header-left">
     <a href="{{ route('cursos.index') }}" class="assistant-brand-logo">
    <img src="{{ asset('img/logo.jpg') }}" alt="Logo ATU">
</a>

            <nav class="assistant-nav">
                <a href="{{ route('cursos.index') }}">Cursos</a>
                <a href="{{ route('orientacion.formulario') }}">Orientación</a>
                <a href="#">Empresas</a>
                <a href="#">Oposiciones</a>
                <a href="#">Blog</a>
            </nav>
        </div>

        <div class="assistant-header-actions">
            <span class="material-symbols-outlined">call</span>
            <span class="material-symbols-outlined">mail</span>
            <span class="material-symbols-outlined">location_on</span>

            <button type="button" class="assistant-student-btn">
                Espacio del Alumno
            </button>
        </div>
    </header>

    <main class="assistant-layout">
        <aside class="assistant-sidebar">
            <div class="assistant-sidebar-content">
                <a href="{{ route('cursos.show', $curso) }}" class="assistant-new-btn">
                    <span class="material-symbols-outlined">arrow_back</span>
                    Volver al curso
                </a>

                <div class="assistant-sidebar-block">
                    <p class="assistant-sidebar-title">Recientes</p>

                    <button type="button" class="assistant-history-item active quick-question">
                        <span class="material-symbols-outlined">chat_bubble</span>
                        <span class="assistant-history-text">
                            <strong>{{ $curso->titulo }}</strong>
                            <small>Curso actual</small>
                        </span>
                    </button>

                    <button type="button" class="assistant-history-item quick-question">
                        <span class="material-symbols-outlined">history</span>
                        <span class="assistant-history-text">
                            <strong>¿Qué requisitos tiene?</strong>
                            <small>Pregunta rápida</small>
                        </span>
                    </button>

                    <button type="button" class="assistant-history-item quick-question">
                        <span class="material-symbols-outlined">history</span>
                        <span class="assistant-history-text">
                            <strong>¿Es online?</strong>
                            <small>Pregunta rápida</small>
                        </span>
                    </button>

                    <button type="button" class="assistant-history-item quick-question">
                        <span class="material-symbols-outlined">history</span>
                        <span class="assistant-history-text">
                            <strong>¿Para quién es?</strong>
                            <small>Pregunta rápida</small>
                        </span>
                    </button>

                    <button type="button" class="assistant-history-item quick-question">
                        <span class="material-symbols-outlined">history</span>
                        <span class="assistant-history-text">
                            <strong>¿Cómo me apunto?</strong>
                            <small>Pregunta rápida</small>
                        </span>
                    </button>
                </div>
            </div>

            <div class="assistant-user-panel">
                <div class="assistant-user-avatar">AT</div>

                <div>
                    <strong>Alumno ATU</strong>
                    <small>Plan Premium</small>
                </div>

                <span class="material-symbols-outlined assistant-settings">settings</span>
            </div>
        </aside>

        <section class="assistant-chat-main">
            <div class="assistant-mobile-title">
                <span class="material-symbols-outlined">menu</span>
                <span>Asistente Virtual</span>
            </div>

            <div class="assistant-messages" id="chatBody">
                <div class="assistant-messages-inner" id="messagesInner">

                    <div class="assistant-welcome">
                        <div class="assistant-welcome-icon">
                            <img src="{{ asset('img/logo.jpg') }}" alt="Logo ATU" class="assistant-welcome-logo">
                        </div>

                        <h1>Hola, soy tu asesor de Grupo ATU</h1>

                        <p>
                            ¿En qué puedo ayudarte hoy con tu formación o digitalización?
                        </p>
                    </div>

                    <div class="assistant-chat-row bot">
                        <div class="assistant-avatar bot">
                            <span class="material-symbols-outlined">smart_toy</span>
                        </div>

                        <div class="assistant-bubble bot">
                            Bienvenido a la plataforma de soporte inteligente de Grupo ATU.
                            Puedo ayudarte a resolver tus dudas sobre este curso.
                            <br><br>
                            <strong>Curso:</strong> {{ $curso->titulo }}<br>
                            <strong>Área:</strong> {{ ucfirst($curso->area) }}<br>
                            <strong>Modalidad:</strong> {{ ucfirst($curso->modalidad) }}<br>
                            <strong>Nivel:</strong> {{ ucfirst($curso->nivel) }}<br>
                            <strong>Ubicación:</strong> {{ $curso->ciudad }}, {{ $curso->provincia }}<br>
                            <strong>Colectivos admitidos:</strong> {{ $curso->situacion_permitida }}
                        </div>
                    </div>

                    <div class="assistant-chat-row bot">
                        <div class="assistant-avatar bot">
                            <span class="material-symbols-outlined">smart_toy</span>
                        </div>

                        <div class="assistant-bubble bot">
                            Actualmente puedo ayudarte con:
                            <ul>
                                <li>○ Requisitos de acceso</li>
                                <li>○ Modalidad y ubicación</li>
                                <li>○ Colectivos admitidos</li>
                                <li>○ Orientación sobre si el curso encaja contigo</li>
                            </ul>
                            ¿Te gustaría recibir información de algún punto en particular?
                        </div>
                    </div>

                </div>
            </div>

            <div class="assistant-input-section">
                <div class="assistant-input-inner">

                    <div class="assistant-quick-chips">
                        <button type="button" class="assistant-chip quick-question">¿Qué requisitos tiene?</button>
                        <button type="button" class="assistant-chip quick-question">¿Cómo me apunto?</button>
                        <button type="button" class="assistant-chip quick-question">¿Pueden acceder desempleados?</button>
                        <button type="button" class="assistant-chip quick-question">¿Es online?</button>
                        <button type="button" class="assistant-chip quick-question">¿Dónde se imparte?</button>
                    </div>

                    <form class="assistant-form" id="chatForm">
                        <button type="button" class="assistant-icon-btn">
                            <span class="material-symbols-outlined">attach_file</span>
                        </button>

                        <input type="text" id="mensajeAlumno" placeholder="Escribe tu consulta aquí..." autocomplete="off">

                        <button type="submit" class="assistant-send-btn">
                            <span class="material-symbols-outlined">send</span>
                        </button>
                    </form>

                    <p class="assistant-helper">
                        El asistente puede cometer errores. Verifica la información académica.
                    </p>
                </div>
            </div>
        </section>
    </main>
</div>

<script>
    const messagesArea = document.getElementById('chatBody');
    const messagesInner = document.getElementById('messagesInner');
    const chatForm = document.getElementById('chatForm');
    const mensajeAlumno = document.getElementById('mensajeAlumno');
    const preguntasRapidas = document.querySelectorAll('.quick-question');

    chatForm.addEventListener('submit', function (event) {
        event.preventDefault();

        const pregunta = mensajeAlumno.value.trim();

        if (pregunta === '') {
            return;
        }

        enviarPregunta(pregunta);
        mensajeAlumno.value = '';
    });

    preguntasRapidas.forEach(function (boton) {
        boton.addEventListener('click', function () {
            let pregunta = boton.textContent.trim();

            if (pregunta === @json($curso->titulo)) {
                pregunta = 'Háblame de este curso';
            }

            enviarPregunta(pregunta);
        });
    });

    function enviarPregunta(pregunta) {
        añadirMensaje(pregunta, 'user');
        preguntarApi(pregunta);
    }

    function añadirMensaje(texto, tipo) {
        const row = document.createElement('div');
        row.classList.add('assistant-chat-row', tipo);

        const avatar = document.createElement('div');
        avatar.classList.add('assistant-avatar', tipo === 'user' ? 'user' : 'bot');

        avatar.innerHTML = tipo === 'user'
            ? '<span class="material-symbols-outlined">person</span>'
            : '<span class="material-symbols-outlined">smart_toy</span>';

        const bubble = document.createElement('div');
        bubble.classList.add('assistant-bubble', tipo === 'user' ? 'user' : 'bot');
        bubble.innerHTML = texto;

        if (tipo === 'user') {
            row.appendChild(bubble);
            row.appendChild(avatar);
        } else {
            row.appendChild(avatar);
            row.appendChild(bubble);
        }

        messagesInner.appendChild(row);
        messagesArea.scrollTop = messagesArea.scrollHeight;
    }

    function mostrarPensando() {
        const row = document.createElement('div');
        row.classList.add('assistant-chat-row', 'bot');
        row.id = 'mensajePensando';

        const avatar = document.createElement('div');
        avatar.classList.add('assistant-avatar', 'bot');
        avatar.innerHTML = '<span class="material-symbols-outlined">smart_toy</span>';

        const bubble = document.createElement('div');
        bubble.classList.add('assistant-bubble', 'bot', 'assistant-typing');
        bubble.innerHTML = `
            <span class="assistant-dot"></span>
            <span class="assistant-dot"></span>
            <span class="assistant-dot"></span>
        `;

        row.appendChild(avatar);
        row.appendChild(bubble);

        messagesInner.appendChild(row);
        messagesArea.scrollTop = messagesArea.scrollHeight;
    }

    function quitarPensando() {
        const pensando = document.getElementById('mensajePensando');

        if (pensando) {
            pensando.remove();
        }
    }

    async function preguntarApi(pregunta) {
        mostrarPensando();

        try {
            const response = await fetch("{{ route('cursos.chat', $curso) }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': "{{ csrf_token() }}"
                },
                body: JSON.stringify({
                    pregunta: pregunta
                })
            });

            const data = await response.json();

            quitarPensando();

            if (data.respuesta) {
                añadirMensaje(data.respuesta, 'bot');
            } else if (data.modo === 'error_api') {
                añadirMensaje(
                    'Error de OpenAI: ' + data.status + '. Revisa la API key, el modelo o el saldo de la cuenta.',
                    'bot'
                );
                console.log(data);
            } else if (data.modo === 'error_exception') {
                añadirMensaje(
                    'Error interno: ' + data.error,
                    'bot'
                );
                console.log(data);
            } else {
                añadirMensaje(
                    'No he podido obtener una respuesta. Revisa la consola del navegador.',
                    'bot'
                );
                console.log(data);
            }

        } catch (error) {
            quitarPensando();

            añadirMensaje(
                'Error conectando con el servidor. Revisa la consola del navegador.',
                'bot'
            );

            console.log(error);
        }
    }
</script>
</body>
</html>