<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ $url }}">
    <meta property="og:title" content="{{ $form->name()->value() }}">
    <meta property="og:description" content="{{ $form->description() ?? 'Fill out this form on FormaFlow' }}">
    <meta property="og:image" content="https://app.formaflow.indeveler.ru/icons/icon-512x512.png">

    <!-- Twitter -->
    <meta property="twitter:card" content="summary_large_image">
    <meta property="twitter:url" content="{{ $url }}">
    <meta property="twitter:title" content="{{ $form->name()->value() }}">
    <meta property="twitter:description" content="{{ $form->description() ?? 'Fill out this form on FormaFlow' }}">
    <meta property="twitter:image" content="https://app.formaflow.indeveler.ru/icons/icon-512x512.png">

    <title>{{ $form->name()->value() }}</title>

    <script>
        window.location.href = "{{ $url }}";
    </script>
</head>
<body>
    <p>Redirecting to form...</p>
</body>
</html>
