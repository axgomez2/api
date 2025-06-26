<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Conexão Realizada - Melhor Envio</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            color: white;
        }

        .container {
            text-align: center;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            padding: 3rem;
            border-radius: 20px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            max-width: 400px;
        }

        .success-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
            animation: bounce 1s ease-in-out;
        }

        h1 {
            margin: 0 0 1rem 0;
            font-size: 1.5rem;
            font-weight: 600;
        }

        p {
            margin: 0 0 2rem 0;
            opacity: 0.9;
            line-height: 1.6;
        }

        .countdown {
            font-size: 1.2rem;
            font-weight: bold;
            color: #ffd700;
        }

        @keyframes bounce {
            0%, 20%, 50%, 80%, 100% {
                transform: translateY(0);
            }
            40% {
                transform: translateY(-10px);
            }
            60% {
                transform: translateY(-5px);
            }
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .container {
            animation: fadeIn 0.5s ease-out;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="success-icon">✅</div>
        <h1>Conexão Realizada!</h1>
        <p>{{ $message }}</p>
        <p>Token válido por {{ $expires_in }} segundos.</p>
        <div class="countdown">
            Fechando em <span id="countdown">3</span> segundos...
        </div>
    </div>

    <script>
        // Fechar o popup após 3 segundos
        let countdown = 3;
        const countdownElement = document.getElementById('countdown');

                const timer = setInterval(() => {
            countdown--;
            countdownElement.textContent = countdown;

            if (countdown <= 0) {
                clearInterval(timer);

                // Tentar comunicar com a janela pai
                if (window.opener) {
                    try {
                        window.opener.postMessage({
                            type: 'MELHOR_ENVIO_SUCCESS',
                            message: 'Token obtido com sucesso'
                        }, '*');
                        console.log('Mensagem enviada para janela pai');
                    } catch (e) {
                        console.log('Erro ao comunicar com janela pai:', e);
                    }
                }

                // Fechar a janela
                window.close();

                // Se não conseguir fechar, mostrar mensagem
                setTimeout(() => {
                    document.body.innerHTML = `
                        <div class="container">
                            <div class="success-icon">✅</div>
                            <h1>Sucesso!</h1>
                            <p>Você pode fechar esta janela agora.</p>
                        </div>
                    `;
                }, 1000);
            }
        }, 1000);
    </script>
</body>
</html>
