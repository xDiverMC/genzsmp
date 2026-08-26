<?php

return [
    'server' => [
        'name' => env('MINECRAFT_SERVER_NAME', 'Genz'),
        'suffix' => env('MINECRAFT_SERVER_SUFFIX', 'SMP'),
        'tagline' => 'GenzSMP Server Minecraft bertema economy survival multiplayer. Bertujuan membangun komunitas server minecraft Indonesia yang ramah, aman dan damai, serta ramai pemain yang Aktif.',
        'host' => env('MINECRAFT_SERVER_HOST', '172.18.0.2'),
        'public_ip' => env('MINECRAFT_PUBLIC_IP', 'genzsmp.site'),
        'java_port' => (int) env('MINECRAFT_JAVA_PORT', 25565),
        'bedrock_port' => (int) env('MINECRAFT_BEDROCK_PORT', 19132),
        'java_version' => '1.20 - 1.26+',
        'bedrock_version' => '1.21 - 1.26+',
        'support_type' => 'Java & Bedrock Edition',
        'discord_url' => 'https://discord.gg/2eWfWUrVMA',
        'whatsapp_group_url' => 'https://chat.whatsapp.com/Cu6tY5tOXsd7AVPGL5in90',
        'whatsapp_owner' => 'https://wa.me/6283132172199',
        'whatsapp_support' => 'https://wa.me/6285877713755',
        'youtube_url' => 'https://youtube.com/c/genzsmp',
        'tiktok_url' => 'https://tiktok.com/@genzsmp.mc',
        'instagram_url' => 'https://instagram.com/genzsmp.mc',
    ],

    'rcon' => [
        'host' => env('MINECRAFT_RCON_HOST', '172.18.0.2'),
        'port' => (int) env('MINECRAFT_RCON_PORT', 25575),
        'password' => env('MINECRAFT_RCON_PASSWORD', 'secret123'),
        'timeout' => (int) env('MINECRAFT_RCON_TIMEOUT', 3),
        'admin_key' => env('ADMIN_RCON_KEY', 'genzsmp_secret_rcon_2026'),
    ],

    'admins' => [
        'admin1' => [
            'name' => 'Fadhil (Admin 1)',
            'role' => 'Server Owner',
            'contact' => 'https://wa.me/6283132172199',
            'description' => 'Pengambil keputusan utama, penanggung jawab jaringan server, dan penanganan urusan krusial/donasi.'
        ],
        'admin2' => [
            'name' => 'Vin (Admin 2)',
            'role' => 'Support & Community',
            'contact' => 'https://wa.me/6285877713755',
            'description' => 'Menangani laporan bug, masalah pemain di game, klaim reward, unban request, dan support harian.'
        ]
    ],

    'ranks' => [
        [
            'id' => 'vip',
            'name' => 'VIP',
            'price' => 'Rp 25.000',
            'raw_price' => 25000,
            'color' => 'from-blue-500 to-cyan-500',
            'rcon_group' => 'vip',
            'description' => 'Rank VIP basic dengan berbagai keuntungan menarik dan bonus koin server.',
            'perks' => [
                'Batas sethome: 5x',
                'Akses perintah /workbench (meja pembuatan portabel)',
                'Akses perintah /ec (enderchest portabel)',
                'Akses perintah /anvil (landasan portabel)',
                'Akses perintah /protect (klaim proteksi)',
                'Akses perintah /skull (mengambil kepala pemain)',
                'Akses perintah /hat (memakai blok di kepala)',
                'Batas claim block (blocklimit): 25k',
                'Bonus Kits VIP',
                'Bonus Money Server sebesar 25k'
            ]
        ],
        [
            'id' => 'mvp',
            'name' => 'MVP',
            'price' => 'Rp 50.000',
            'raw_price' => 50000,
            'color' => 'from-green-500 to-emerald-500',
            'rcon_group' => 'mvp',
            'description' => 'Rank menengah dengan akses terbang (/fly) dan kustomisasi nama.',
            'perks' => [
                'Batas sethome: 8x',
                'Akses perintah /feed (mengisi rasa lapar)',
                'Akses perintah /fly (bisa terbang)',
                'Akses perintah /nickname (kustom nama panggilan)',
                'Akses perintah /heal (memulihkan darah)',
                'Akses perintah /suicide (bunuh diri)',
                'Akses perintah /beezooka (menembakkan lebah)',
                'Batas claim block (blocklimit): 50k',
                'Bonus Kits Master',
                'Bonus Money Server sebesar 50k'
            ]
        ],
        [
            'id' => 'eternal',
            'name' => 'ETERNAL',
            'price' => 'Rp 75.000',
            'raw_price' => 75000,
            'color' => 'from-indigo-500 to-purple-500',
            'rcon_group' => 'eternal',
            'description' => 'Kekuatan abadi dengan kemampuan memperbaiki item dan kekuatan petir.',
            'perks' => [
                'Batas sethome: 12x',
                'Akses perintah /repair (memperbaiki item gratis)',
                'Akses perintah /lightning (menurunkan petir kosmetik)',
                'Batas claim block (blocklimit): 75k',
                'Bonus Kits Eternal',
                'Bonus Money Server sebesar 75k'
            ]
        ],
        [
            'id' => 'astral',
            'name' => 'ASTRAL',
            'price' => 'Rp 100.000',
            'raw_price' => 100000,
            'color' => 'from-pink-500 to-rose-500',
            'rcon_group' => 'astral',
            'description' => 'Kekuasaan kosmik dengan pandangan malam tanpa batas dan pengatur waktu.',
            'perks' => [
                'Batas sethome: 15x',
                'Akses perintah /vision (nightvision gratis)',
                'Akses perintah /time set (mengatur waktu lokal)',
                'Batas claim block (blocklimit): 100k',
                'Bonus Kits Astral',
                'Bonus Money Server sebesar 100k'
            ]
        ],
        [
            'id' => 'master',
            'name' => 'MASTER',
            'price' => 'Rp 150.000',
            'raw_price' => 150000,
            'color' => 'from-amber-500 to-yellow-500',
            'rcon_group' => 'master',
            'description' => 'Rank Master dengan kekebalan dewa, kontrol cuaca, dan kustomisasi nama item.',
            'perks' => [
                'Batas sethome: 20x',
                'Akses perintah /weather (mengatur cuaca lokal)',
                'Akses perintah /egod (kebal terhadap damage tertentu)',
                'Akses perintah /itemname (mengubah nama item di tangan)',
                'Batas claim block (blocklimit): 125k',
                'Bonus Kits Master',
                'Bonus Money Server sebesar 125k'
            ]
        ],
        [
            'id' => 'celestial',
            'name' => 'CELESTIAL',
            'price' => 'Rp 175.000',
            'raw_price' => 175000,
            'color' => 'from-purple-500 to-fuchsia-500',
            'rcon_group' => 'celestial',
            'description' => 'Rank surgawi dengan akses teleportasi instan dan kecepatan super.',
            'perks' => [
                'Batas sethome: 24x',
                'Akses perintah /tp (teleportasi instan)',
                'Akses perintah /speed (mengatur kecepatan gerak)',
                'Akses perintah /firework (membuat kembang api)',
                'Batas claim block (blocklimit): 150k',
                'Bonus Kits Celestial',
                'Bonus Money Server sebesar 150k'
            ]
        ],
        [
            'id' => 'supreme',
            'name' => 'SUPREME',
            'price' => 'Rp 200.000',
            'raw_price' => 200000,
            'color' => 'from-red-500 to-orange-500',
            'rcon_group' => 'supreme',
            'description' => 'Rank kasta tertinggi dengan kemampuan menembakkan bola api dan mengatur warp pribadi.',
            'perks' => [
                'Batas sethome: 30x',
                'Akses perintah /setwarp (membuat titik warp kustom)',
                'Akses perintah /fireball (menembakkan bola api)',
                'Batas claim block (blocklimit): 175k',
                'Bonus Kits Supreme',
                'Bonus Money Server sebesar 175k'
            ]
        ],
        [
            'id' => 'legend',
            'name' => 'LEGEND',
            'price' => 'Rp 250.000',
            'raw_price' => 250000,
            'color' => 'from-amber-500 to-red-500',
            'rcon_group' => 'legend',
            'description' => 'Rank legenda terkuat. Akses mode penonton dan efek ramuan tanpa batas.',
            'perks' => [
                'Batas sethome: 35x',
                'Akses perintah /potion (efek ramuan permanen)',
                'Akses perintah /spectator (mode penonton)',
                'Batas claim block (blocklimit): 250k',
                'Bonus Kits Legend',
                'Bonus Money Server sebesar 250k'
            ]
        ],
    ],

    'money_packages' => [
        ['id' => 'money_1m', 'name' => 'Money 1M (1.000.000)', 'price' => 'Rp 15.000', 'raw_price' => 15000, 'amount' => 1000000],
        ['id' => 'money_2m', 'name' => 'Money 2M (2.000.000)', 'price' => 'Rp 30.000', 'raw_price' => 30000, 'amount' => 2000000],
        ['id' => 'money_3m', 'name' => 'Money 3M (3.000.000)', 'price' => 'Rp 45.000', 'raw_price' => 45000, 'amount' => 3000000],
        ['id' => 'money_5m', 'name' => 'Money 5M (5.000.000)', 'price' => 'Rp 75.000', 'raw_price' => 75000, 'amount' => 5000000],
        ['id' => 'money_10m', 'name' => 'Money 10M (10.000.000)', 'price' => 'Rp 150.000', 'raw_price' => 150000, 'amount' => 10000000],
    ],

    'skill_packages' => [
        ['id' => 'skill_1l', 'name' => 'Skills 1 Level', 'price' => 'Rp 2.000', 'raw_price' => 2000, 'levels' => 1],
        ['id' => 'skill_5l', 'name' => 'Skills 5 Level', 'price' => 'Rp 10.000', 'raw_price' => 10000, 'levels' => 5],
        ['id' => 'skill_10l', 'name' => 'Skills 10 Level', 'price' => 'Rp 20.000', 'raw_price' => 20000, 'levels' => 10],
        ['id' => 'skill_25l', 'name' => 'Skills 25 Level', 'price' => 'Rp 50.000', 'raw_price' => 50000, 'levels' => 25],
        ['id' => 'skill_50l', 'name' => 'Skills 50 Level', 'price' => 'Rp 100.000', 'raw_price' => 100000, 'levels' => 50],
        ['id' => 'skill_100l', 'name' => 'Skills 100 Level', 'price' => 'Rp 200.000', 'raw_price' => 200000, 'levels' => 100],
    ],

    'features' => [
        ['id' => 'economy', 'name' => 'Economy', 'icon' => 'Coins', 'description' => 'Sistem keuangan yang seimbang, kumpulkan Money untuk bertransaksi dengan player lain.'],
        ['id' => 'jobs', 'name' => 'Jobs', 'icon' => 'Briefcase', 'description' => 'Pilih dari 10+ pekerjaan berbeda seperti Penambang, Penebang Pohon, Pemburu untuk menghasilkan uang.'],
        ['id' => 'skills', 'name' => 'Skills', 'icon' => 'Flame', 'description' => 'Sistem McMMO premium. Tingkatkan level skill bertarung, menambang, dan bertani untuk unlock skill aktif.'],
        ['id' => 'crates', 'name' => 'Crater & Gacha', 'icon' => 'Gift', 'description' => 'Dapatkan Kunci Crate dari event, vote, atau quest untuk mendapatkan item legendaris, kosmetik, dan rank.'],
        ['id' => 'auction', 'name' => 'Auction', 'icon' => 'ShoppingBag', 'description' => 'Lelang item terbaikmu di pasar global (Auction House) dan tawar item langka milik pemain lain secara real-time.'],
        ['id' => 'daily_reward', 'name' => 'Daily Reward', 'icon' => 'CalendarCheck', 'description' => 'Login setiap hari dan klaim hadiah beruntun yang semakin menarik setiap harinya untuk membantu survivalmu.'],
        ['id' => 'rtp', 'name' => 'RTP (Wild Teleport)', 'icon' => 'Compass', 'description' => 'Teleportasi acak ke dunia survival yang tak terbatas secara instan tanpa perlu berjalan jauh dari spawn.'],
        ['id' => 'shop', 'name' => 'Shop', 'icon' => 'Store', 'description' => 'Toko server lengkap untuk membeli blok bangunan, material, dekorasi, hingga menjual hasil panenmu.'],
        ['id' => 'quest', 'name' => 'Quest & Misi', 'icon' => 'BookOpen', 'description' => 'Selesaikan misi harian, mingguan, dan misi cerita RPG untuk mendapatkan EXP, koin, dan item legendaris.']
    ],

    'skills_info' => [
        ['id' => 'mining', 'name' => 'Mining & Excavation', 'description' => 'Tingkatkan skill menambang untuk kesempatan double-drops diamond, durabilitas tools instan, dan mode pengeboran super kencang.', 'levels' => 'Max Level 1000'],
        ['id' => 'combat', 'name' => 'Swords & Archery', 'description' => 'Tingkatkan skill bertarung untuk membuka serangan critical beruntun, counter-attack otomatis, dan tembakan panah berapi ganda.', 'levels' => 'Max Level 1000'],
        ['id' => 'woodcutting', 'name' => 'Woodcutting (Tree Feller)', 'description' => 'Tingkatkan skill menebang untuk mengaktifkan fitur TreeFeller (menebang satu pohon utuh dalam sekali pukul) dan ekstra kayu.', 'levels' => 'Max Level 1000'],
        ['id' => 'farming', 'name' => 'Herbalism & Farming', 'description' => 'Tingkatkan skill bertani untuk menanam kembali tanaman otomatis (auto-replant), double harvest gandum/wortel, dan membuat ramuan instan.', 'levels' => 'Max Level 1000']
    ],

    'vote_links' => [
        ['id' => 'minecraft_mp', 'name' => 'Minecraft-MP', 'url' => 'https://minecraft-mp.com/server/360105/vote/', 'icon' => 'Vote']
    ],

    'rules' => [
        ['number' => 1, 'title' => 'Hormati sesama pemain.', 'description' => 'Selalu bersikap sopan dan ramah di chat global. Hargai perbedaan agama, ras, dan suku pemain lainnya.'],
        ['number' => 2, 'title' => 'Dilarang toxic.', 'description' => 'Dilarang keras melontarkan kata-kata kotor, kasar, rasis, provokatif, SARA, atau melakukan cyberbullying.'],
        ['number' => 3, 'title' => 'Dilarang spam.', 'description' => 'Jangan membanjiri chat global dengan pesan yang sama berulang kali, huruf kapital berlebih, atau link mencurigakan.'],
        ['number' => 4, 'title' => 'Gunakan nama yang sopan.', 'description' => 'Nama karakter (Username), nama pet, nama klan, serta tulisan di papan sign harus sopan dan tidak mengandung unsur SARA.'],
        ['number' => 5, 'title' => 'Ikuti arahan staff.', 'description' => 'Hormati keputusan moderator, helper, dan admin. Keputusan staff bersifat mutlak dan tidak bisa diganggu gugat.'],
        ['number' => 6, 'title' => 'Jangan abuse bug.', 'description' => 'Jika menemukan bug/glitch dalam server, wajib segera melaporkannya ke staff Discord. Memanfaatkan bug untuk diri sendiri akan disanksi.']
    ],

    'bans' => [
        ['title' => 'Scam Player', 'description' => 'Menipu atau melakukan scam terhadap pemain lain dalam transaksi apapun.', 'severity' => 'Ban 3 Hari'],
        ['title' => 'Rusuh ke Pemain Tak Bersalah', 'description' => 'Mengganggu atau berbuat onar terhadap pemain yang tidak bersalah.', 'severity' => 'Ban 3 Hari'],
        ['title' => 'Rusuh Base Orang Lain', 'description' => 'Merusak atau mengacak-ngacak base/bangunan milik pemain lain.', 'severity' => 'Ban 3 Hari'],
        ['title' => 'Rusuh / Grief Server', 'description' => 'Merusak fasilitas atau area milik server (grief) secara sengaja.', 'severity' => 'Ban IP'],
        ['title' => 'Cheating', 'description' => 'Menggunakan cheat, hack client, atau modifikasi ilegal apapun.', 'severity' => 'Ban 3 Hari'],
        ['title' => 'Ganggu Player Baru', 'description' => 'Mengganggu atau merugikan pemain baru yang belum berpengalaman.', 'severity' => 'Ban 1 Hari'],
        ['title' => 'Toxic di Server', 'description' => 'Bersikap toxic, kasar, atau provokatif di chat server.', 'severity' => 'Mute 4 Jam'],
        ['title' => 'Sering Toxic (Berulang)', 'description' => 'Melakukan sikap toxic secara berulang setelah sebelumnya sudah ditegur.', 'severity' => 'Mute 12 Jam'],
        ['title' => 'Bahas Konten 18+', 'description' => 'Membahas atau menyebarkan konten dewasa (18+) di chat server.', 'severity' => 'Mute 6 Jam'],
        ['title' => 'Punya Rank Tapi Rusuh ke Member', 'description' => 'Memiliki rank donatur namun tetap berbuat rusuh terhadap player rank member.', 'severity' => 'Ban 3 Hari'],
        ['title' => 'Memanfaatkan Bug', 'description' => 'Sengaja memanfaatkan bug/glitch server untuk keuntungan pribadi.', 'severity' => 'Ban 1 Hari'],
        ['title' => 'Scam Arqoah', 'description' => 'Melakukan scam terkait transaksi Arqoah.', 'severity' => 'Ban 1 Hari'],
        ['title' => 'Kill Player', 'description' => 'Membunuh pemain lain di luar ketentuan yang diperbolehkan.', 'severity' => 'Ban 3 Hari']
    ],

    'trading' => [
        'ws_port' => (int) env('ARQOINVEST_WS_PORT', 8088),
        'ws_host' => env('ARQOINVEST_WS_HOST', '178.128.105.129'),
        'tax_percent' => 8.0,
        'cooldown_seconds' => 5,
        'session_ttl_seconds' => 900,
        'assets' => [
            'btc' => [
                'symbol' => 'BTC',
                'name' => 'Bitcoin',
                'category' => 'Crypto',
                'icon' => 'Coins',
                'color' => '#F7931A',
                'initial_price' => 1020,
                'min_price' => 500,
                'max_price' => 100000,
                'tax_percent' => 8.0,
                'description' => 'Aset crypto terkemuka dengan kapitalisasi pasar terbesar di GenzSMP.'
            ],
            'eth' => [
                'symbol' => 'ETH',
                'name' => 'Ethereum',
                'category' => 'Crypto',
                'icon' => 'Zap',
                'color' => '#627EEA',
                'initial_price' => 510,
                'min_price' => 250,
                'max_price' => 50000,
                'tax_percent' => 8.0,
                'description' => 'Platform smart contract dan aset desentralisasi utilitas tinggi.'
            ],
            'gld' => [
                'symbol' => 'GLD',
                'name' => 'Gold Ingot',
                'category' => 'Commodity',
                'icon' => 'Shield',
                'color' => '#FFD700',
                'initial_price' => 105,
                'min_price' => 50,
                'max_price' => 5000,
                'tax_percent' => 8.0,
                'description' => 'Komoditas emas fisik stabil dengan risiko volatilitas rendah.'
            ],
            'dia' => [
                'symbol' => 'DIA',
                'name' => 'Diamond Gem',
                'category' => 'Commodity',
                'icon' => 'Gem',
                'color' => '#00E5FF',
                'initial_price' => 245,
                'min_price' => 100,
                'max_price' => 10000,
                'tax_percent' => 8.0,
                'description' => 'Aset permata berharga hasil tambang dalam dengan permintaan tinggi.'
            ],
            'emd' => [
                'symbol' => 'EMD',
                'name' => 'Emerald Shard',
                'category' => 'Commodity',
                'icon' => 'Sparkles',
                'color' => '#00E676',
                'initial_price' => 175,
                'min_price' => 80,
                'max_price' => 8000,
                'tax_percent' => 8.0,
                'description' => 'Mata uang barter villager dengan likuiditas tinggi di pasar global.'
            ],
        ]
    ]
];
