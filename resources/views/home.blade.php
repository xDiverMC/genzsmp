<!doctype html>
<html lang="id" class="scroll-smooth bg-[#0f0f0f] text-neutral-200">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>{{ $serverInfo['name'] }} {{ $serverInfo['suffix'] }} - Server Minecraft Indonesia Terpopuler</title>
    <meta name="description" content="{{ $serverInfo['tagline'] }}" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />

    <!-- Favicon & App Icons -->
    <link rel="icon" type="image/png" href="/images/logo.png" />
    <link rel="shortcut icon" type="image/png" href="/images/logo.png" />
    <link rel="apple-touch-icon" href="/images/logo.png" />
    <link rel="manifest" href="/manifest.json" />
    <meta name="theme-color" content="#8b5cf6" />

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800;900&family=Space+Grotesk:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;700&display=swap" rel="stylesheet" />

    <!-- Tailwind CSS v3 CDN -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Lucide Icons CDN (UMD browser bundle) -->
    <script src="https://unpkg.com/lucide@0.475.0/dist/umd/lucide.min.js"></script>

    <!-- React 18 & ReactDOM 18 CDNs -->
    <script src="https://unpkg.com/react@18/umd/react.production.min.js" crossorigin></script>
    <script src="https://unpkg.com/react-dom@18/umd/react-dom.production.min.js" crossorigin></script>

    <!-- Babel Standalone for live in-browser JSX compilation -->
    <script src="https://unpkg.com/@babel/standalone/babel.min.js"></script>

    <!-- Custom Tailwind Configuration -->
    <script>
      tailwind.config = {
        theme: {
          extend: {
            colors: {
              primary: '#a855f7', // purple-500
              secondary: '#f97316', // orange-500
              'dark-bg': '#0f0f0f',
            },
            fontFamily: {
              sans: ['"Plus Jakarta Sans"', 'Inter', 'sans-serif'],
              display: ['"Space Grotesk"', 'sans-serif'],
              mono: ['"JetBrains Mono"', 'monospace'],
            }
          }
        }
      }
    </script>

    <!-- Custom Core CSS Styles -->
    <style>
      /* Custom Scrollbar */
      ::-webkit-scrollbar {
        width: 10px;
      }
      ::-webkit-scrollbar-track {
        background: #0f0f0f;
      }
      ::-webkit-scrollbar-thumb {
        background: #2a2a2a;
        border-radius: 5px;
        border: 2px solid #0f0f0f;
      }
      ::-webkit-scrollbar-thumb:hover {
        background: #a855f7;
      }

      /* Glassmorphism custom classes */
      .glass-card {
        background: rgba(15, 15, 15, 0.7);
        backdrop-filter: blur(12px);
        -webkit-backdrop-filter: blur(12px);
        border: 1px solid rgba(168, 85, 247, 0.12);
        box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.4);
      }

      .glass-card-hover {
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
      }

      .glass-card-hover:hover {
        background: rgba(15, 15, 15, 0.85);
        border-color: rgba(168, 85, 247, 0.35);
        box-shadow: 0 12px 40px 0 rgba(168, 85, 247, 0.08);
        transform: translateY(-4px);
      }

      .glass-nav {
        background: rgba(15, 15, 15, 0.8);
        backdrop-filter: blur(16px);
        -webkit-backdrop-filter: blur(16px);
        border-bottom: 1px solid rgba(168, 85, 247, 0.08);
      }

      .text-glow {
        text-shadow: 0 0 15px rgba(168, 85, 247, 0.5);
      }

      .box-glow {
        box-shadow: 0 0 20px rgba(168, 85, 247, 0.25);
      }

      /* Transitions and animations */
      .fade-in-up {
        animation: fadeInUp 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards;
      }

      @keyframes fadeInUp {
        from {
          opacity: 0;
          transform: translateY(20px);
        }
        to {
          opacity: 1;
          transform: translateY(0);
        }
      }
    </style>
  </head>
  <body>
    <div id="root"></div>

    <!-- Data injected from Laravel Controller -->
    <script>
      window.LARAVEL_SERVER_DATA = {
        serverInfo: @json($serverInfo),
        admins: @json($admins),
        ranks: @json($ranks),
        moneyPackages: @json($moneyPackages),
        skillPackages: @json($skillPackages),
        features: @json($features),
        skillsInfo: @json($skillsInfo),
        voteLinks: @json($voteLinks),
        rules: @json($rules),
        bans: @json($bans),
        allCheckoutItems: @json($allCheckoutItems),
        initialStatus: @json($initialStatus),
        csrfToken: "{{ csrf_token() }}",
        images: {
          heroBg: "/images/background.png",
          townImg: "/images/fotbar1.png",
          dungeonImg: "/images/fotbar2.png",
          pvpImg: "/images/fotbar3.png",
          logoImg: "/images/logo.png"
        }
      };
    </script>

    <!-- React Application -->
    <script type="text/babel" src="/js/app.js"></script>
  </body>
</html>
