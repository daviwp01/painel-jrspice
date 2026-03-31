<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
</head>
<body style="font-family: 'Inter', Helvetica, Arial, sans-serif; background-color: #f8fafc; color: #1e293b; line-height: 1.6; padding: 20px; margin: 0;">
    <div class="container" style="max-width: 600px; margin: 20px auto; background: #ffffff; border-radius: 24px; padding: 40px; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1);">
        <div class="header" style="border-bottom: 2px solid #3b82f6; padding-bottom: 20px; margin-bottom: 30px;">
            <h1 style="margin: 0; font-size: 18px; font-weight: 900; text-transform: uppercase; letter-spacing: 2px; color: #1e293b;">
                Contato Direto <span style="color: #3b82f6;">SISTEMA JR SPICE</span>
            </h1>
        </div>
        
        <p style="font-weight: 600; color: #64748b; margin-bottom: 25px; font-size: 13px;">
            Um novo contato foi enviado através do formulário oficial do sistema por um usuário autenticado.
        </p>

        <div class="info-list" style="margin-bottom: 30px;">
            <!-- Nome -->
            <div class="info-item" style="background: #f1f5f9; padding: 15px 25px; border-radius: 16px; margin-bottom: 12px;">
                <span class="label" style="font-size: 10px; font-weight: 900; text-transform: uppercase; letter-spacing: 1.5px; color: #64748b; display: block; margin-bottom: 4px;">Nome do Usuário</span>
                <span class="value" style="font-size: 15px; font-weight: 700; color: #0f172a; word-break: break-all;">{{ $data['name'] }}</span>
            </div>
            
            <!-- Email -->
            <div class="info-item" style="background: #f1f5f9; padding: 15px 25px; border-radius: 16px; margin-bottom: 12px;">
                <span class="label" style="font-size: 10px; font-weight: 900; text-transform: uppercase; letter-spacing: 1.5px; color: #64748b; display: block; margin-bottom: 4px;">E-mail de Resposta</span>
                <span class="value" style="font-size: 15px; font-weight: 700; color: #3b82f6; word-break: break-all;">{{ $data['email'] }}</span>
            </div>

            <!-- Empresa -->
            <div class="info-item" style="background: #f1f5f9; padding: 15px 25px; border-radius: 16px; margin-bottom: 12px;">
                <span class="label" style="font-size: 10px; font-weight: 900; text-transform: uppercase; letter-spacing: 1.5px; color: #64748b; display: block; margin-bottom: 4px;">Empresa</span>
                <span class="value" style="font-size: 15px; font-weight: 700; color: #0f172a;">{{ $data['company'] ?? 'Não informada' }}</span>
            </div>

            <!-- Telefone -->
            <div class="info-item" style="background: #f1f5f9; padding: 15px 25px; border-radius: 16px; margin-bottom: 12px;">
                <span class="label" style="font-size: 10px; font-weight: 900; text-transform: uppercase; letter-spacing: 1.5px; color: #64748b; display: block; margin-bottom: 4px;">Telefone</span>
                <span class="value" style="font-size: 15px; font-weight: 700; color: #0f172a;">{{ $data['phone'] ?? 'Não informado' }}</span>
            </div>

            <!-- Assunto -->
            <div class="info-item" style="background: #f1f5f9; padding: 15px 25px; border-radius: 16px; margin-bottom: 12px;">
                <span class="label" style="font-size: 10px; font-weight: 900; text-transform: uppercase; letter-spacing: 1.5px; color: #64748b; display: block; margin-bottom: 4px;">Assunto</span>
                <span class="value" style="font-size: 15px; font-weight: 700; color: #0f172a;">{{ $data['subject'] }}</span>
            </div>
        </div>

        <span class="message-label" style="font-size: 10px; font-weight: 900; text-transform: uppercase; letter-spacing: 1.5px; color: #64748b; margin-top: 30px; margin-bottom: 10px; display: block;">Mensagem:</span>
        <div class="message-box" style="background: #ffffff; border: 1px solid #e2e8f0; padding: 25px; border-radius: 20px; min-height: 100px; color: #334155; font-size: 14px;">
            {!! nl2br(e($data['message'])) !!}
        </div>

        <div class="footer" style="text-align: center; margin-top: 50px; font-size: 9px; font-weight: 800; color: #94a3b8; text-transform: uppercase; letter-spacing: 2px; border-top: 1px dashed #e2e8f0; padding-top: 20px;">
            SISTEMA DE GERENCIAMENTO JRSPICE &bull; AUTOMATED NOTIFICATION
        </div>
    </div>
</body>
</html>
