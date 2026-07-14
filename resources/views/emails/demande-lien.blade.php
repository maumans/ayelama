<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>{{ $officeNom }}</title>
</head>
<body style="font-family: Arial, sans-serif; background:#F5F5F3; padding:32px 0; margin:0;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
        <tr>
            <td align="center">
                <table role="presentation" width="480" cellpadding="0" cellspacing="0" style="background:#ffffff; border-radius:12px; padding:32px; border:1px solid #e5e5e5;">
                    <tr>
                        <td style="font-size:18px; font-weight:bold; color:#15263F; padding-bottom:8px;">
                            {{ $officeNom }}
                        </td>
                    </tr>
                    <tr>
                        <td style="font-size:14px; color:#333; padding-bottom:16px;">
                            Bonjour,<br><br>
                            Pour avancer sur votre dossier @if($typeActe) (<strong>{{ $typeActe }}</strong>) @endif,
                            merci de compléter vos informations en suivant le lien ci-dessous.
                            Vous pouvez soit saisir vos informations directement, soit téléverser
                            une photo de votre pièce d'identité ou d'une fiche déjà remplie.
                        </td>
                    </tr>
                    <tr>
                        <td align="center" style="padding: 16px 0;">
                            <a href="{{ $lien }}" style="background:#B0863C; color:#ffffff; text-decoration:none; padding:12px 24px; border-radius:8px; font-weight:bold; display:inline-block;">
                                Compléter mes informations
                            </a>
                        </td>
                    </tr>
                    <tr>
                        <td style="font-size:12px; color:#888;">
                            Ce lien est valable jusqu'au {{ $expireAt }}. Si vous n'êtes pas à
                            l'origine de cette démarche, vous pouvez ignorer ce message.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
