<!doctype html>
<html lang="id" class="bg-[#0f0f0f] text-neutral-200">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Admin RCON Console - Genz SMP</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&family=JetBrains+Mono:wght@400;700&display=swap" rel="stylesheet" />
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@0.475.0/dist/umd/lucide.min.js"></script>
    <script>
      tailwind.config = {
        theme: {
          extend: {
            colors: {
              primary: '#a855f7',
              'dark-bg': '#0f0f0f',
            },
            fontFamily: {
              sans: ['"Plus Jakarta Sans"', 'sans-serif'],
              mono: ['"JetBrains Mono"', 'monospace'],
            }
          }
        }
      }
    </script>
  </head>
  <body class="min-h-screen bg-[#0f0f0f] font-sans p-6 text-neutral-200">
    <div class="max-w-6xl mx-auto space-y-6">

      <!-- Header -->
      <div class="flex items-center justify-between border-b border-neutral-800 pb-6">
        <div class="flex items-center gap-3">
          <div class="h-10 w-10 rounded-xl bg-purple-500/10 border border-purple-500/30 flex items-center justify-center text-primary">
            <i data-lucide="terminal" class="h-6 w-6"></i>
          </div>
          <div>
            <h1 class="text-2xl font-black text-white">GENZSMP RCON & SERVER CONSOLE</h1>
            <p class="text-xs text-neutral-400">Laravel Remote Management & Player Order Delivery</p>
          </div>
        </div>
        <div class="flex items-center gap-3">
          <a href="{{ route('home') }}" class="px-4 py-2 text-xs font-bold uppercase rounded-xl bg-neutral-900 border border-neutral-800 hover:text-white transition">
            Lihat Web Portal
          </a>
          <span class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full text-xs font-bold {{ $status['online'] ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : 'bg-red-500/10 text-red-400 border border-red-500/20' }}">
            <span class="h-2 w-2 rounded-full {{ $status['online'] ? 'bg-emerald-400 animate-pulse' : 'bg-red-400' }}"></span>
            Server {{ $status['online'] ? 'ONLINE (' . $status['players']['online'] . '/' . $status['players']['max'] . ')' : 'OFFLINE' }}
          </span>
        </div>
      </div>

      <!-- Flash Messages -->
      @if (session('success'))
        <div class="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-sm font-medium flex items-center gap-2">
          <i data-lucide="check-circle" class="h-5 w-5 shrink-0"></i>
          <span>{{ session('success') }}</span>
        </div>
      @endif

      @if (session('error'))
        <div class="p-4 rounded-xl bg-red-500/10 border border-red-500/30 text-red-400 text-sm font-medium flex items-center gap-2">
          <i data-lucide="alert-triangle" class="h-5 w-5 shrink-0"></i>
          <span>{{ session('error') }}</span>
        </div>
      @endif

      <!-- Grid Layout -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- Column 1 & 2: Terminal & Quick Actions -->
        <div class="lg:col-span-2 space-y-6">

          <!-- Command Terminal Box -->
          <div class="p-6 rounded-2xl bg-neutral-950 border border-neutral-800 space-y-4 shadow-xl">
            <h2 class="text-sm font-bold uppercase tracking-wider text-primary flex items-center gap-2">
              <i data-lucide="command" class="h-4 w-4"></i> Eksekusi Command RCON
            </h2>

            <form action="{{ route('admin.rcon.execute') }}" method="POST" class="space-y-3">
              @csrf
              <div class="flex gap-2">
                <input 
                  type="text" 
                  name="command" 
                  placeholder="Contoh: list, say Halo GenzSMP!, give Steve diamond 64..." 
                  required
                  class="flex-1 px-4 py-3 rounded-xl bg-neutral-900 border border-neutral-800 focus:border-primary/50 text-white font-mono text-sm focus:outline-none placeholder-neutral-600"
                />
                <button type="submit" class="px-6 py-3 rounded-xl bg-gradient-to-r from-primary to-purple-600 font-bold text-xs uppercase tracking-wider text-white hover:brightness-110 active:scale-95 transition">
                  Kirim
                </button>
              </div>
            </form>

            <!-- Quick Command Shortcuts -->
            <div class="pt-2 flex flex-wrap gap-2 text-xs">
              <span class="text-neutral-500 self-center">Shortcut:</span>
              <form action="{{ route('admin.rcon.execute') }}" method="POST" class="inline">
                @csrf
                <input type="hidden" name="command" value="list" />
                <button type="submit" class="px-2.5 py-1 rounded-lg bg-neutral-900 border border-neutral-800 hover:border-primary text-neutral-300 font-mono">/list</button>
              </form>
              <form action="{{ route('admin.rcon.execute') }}" method="POST" class="inline">
                @csrf
                <input type="hidden" name="command" value="tps" />
                <button type="submit" class="px-2.5 py-1 rounded-lg bg-neutral-900 border border-neutral-800 hover:border-primary text-neutral-300 font-mono">/tps</button>
              </form>
              <form action="{{ route('admin.rcon.execute') }}" method="POST" class="inline">
                @csrf
                <input type="hidden" name="command" value="gc" />
                <button type="submit" class="px-2.5 py-1 rounded-lg bg-neutral-900 border border-neutral-800 hover:border-primary text-neutral-300 font-mono">/gc</button>
              </form>
              <form action="{{ route('admin.rcon.execute') }}" method="POST" class="inline">
                @csrf
                <input type="hidden" name="command" value="save-all" />
                <button type="submit" class="px-2.5 py-1 rounded-lg bg-neutral-900 border border-neutral-800 hover:border-primary text-neutral-300 font-mono">/save-all</button>
              </form>
            </div>
          </div>

          <!-- Quick Rank Assignment Panel -->
          <div class="p-6 rounded-2xl bg-neutral-950 border border-neutral-800 space-y-4">
            <h2 class="text-sm font-bold uppercase tracking-wider text-primary flex items-center gap-2">
              <i data-lucide="award" class="h-4 w-4"></i> Berikan Rank ke Player (LuckPerms)
            </h2>

            <form action="{{ route('admin.rcon.execute') }}" method="POST" class="grid grid-cols-1 sm:grid-cols-3 gap-3">
              @csrf
              <input 
                type="text" 
                name="player" 
                id="target_player"
                placeholder="Gamertag Player" 
                required
                class="px-4 py-2.5 rounded-xl bg-neutral-900 border border-neutral-800 text-white font-mono text-sm focus:outline-none"
              />
              <select 
                name="rank" 
                id="target_rank"
                class="px-4 py-2.5 rounded-xl bg-neutral-900 border border-neutral-800 text-white text-sm focus:outline-none"
              >
                @foreach ($ranks as $rank)
                  <option value="{{ strtolower($rank['name']) }}">{{ $rank['name'] }}</option>
                @endforeach
              </select>
              <button 
                type="button" 
                onclick="submitRankForm()"
                class="px-4 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 font-bold text-xs uppercase text-white transition"
              >
                Assign Rank
              </button>
            </form>
          </div>

          <!-- Recent Orders Management -->
          <div class="p-6 rounded-2xl bg-neutral-950 border border-neutral-800 space-y-4">
            <div class="flex items-center justify-between">
              <h2 class="text-sm font-bold uppercase tracking-wider text-primary flex items-center gap-2">
                <i data-lucide="shopping-bag" class="h-4 w-4"></i> Daftar Pesanan Web (Checkout)
              </h2>
              <span class="text-xs text-neutral-500">{{ $recentOrders->count() }} Pesanan</span>
            </div>

            <div class="overflow-x-auto">
              <table class="w-full text-left text-xs">
                <thead>
                  <tr class="border-b border-neutral-800 text-neutral-400 font-semibold uppercase">
                    <th class="py-2.5 px-3">ID</th>
                    <th class="py-2.5 px-3">Player</th>
                    <th class="py-2.5 px-3">Paket</th>
                    <th class="py-2.5 px-3">Harga</th>
                    <th class="py-2.5 px-3">Metode</th>
                    <th class="py-2.5 px-3">Status</th>
                    <th class="py-2.5 px-3">Aksi</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-neutral-900 text-neutral-300">
                  @forelse ($recentOrders as $order)
                    <tr>
                      <td class="py-2.5 px-3 font-mono text-neutral-500">#{{ $order->id }}</td>
                      <td class="py-2.5 px-3 font-bold text-white">{{ $order->gamertag }}</td>
                      <td class="py-2.5 px-3">{{ $order->item_name }}</td>
                      <td class="py-2.5 px-3 text-primary font-mono font-bold">{{ $order->price }}</td>
                      <td class="py-2.5 px-3">{{ $order->payment_method }}</td>
                      <td class="py-2.5 px-3">
                        <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase {{ $order->status === 'delivered' ? 'bg-emerald-500/10 text-emerald-400' : 'bg-yellow-500/10 text-yellow-400' }}">
                          {{ $order->status }}
                        </span>
                      </td>
                      <td class="py-2.5 px-3">
                        @if ($order->status !== 'delivered')
                          <form action="{{ route('admin.rcon.deliver', $order->id) }}" method="POST" class="inline">
                            @csrf
                            <button type="submit" class="px-2.5 py-1 rounded bg-purple-600/30 hover:bg-purple-600 text-purple-200 text-[10px] font-bold uppercase transition">
                              Kirim RCON
                            </button>
                          </form>
                        @else
                          <span class="text-neutral-500 text-[10px]">Terkirim</span>
                        @endif
                      </td>
                    </tr>
                  @empty
                    <tr>
                      <td colspan="7" class="py-6 text-center text-neutral-500">Belum ada pesanan masuk.</td>
                    </tr>
                  @endforelse
                </tbody>
              </table>
            </div>
          </div>

        </div>

        <!-- Column 3: Telemetry & Audit Logs -->
        <div class="space-y-6">

          <!-- Server Telemetry Card -->
          <div class="p-6 rounded-2xl bg-neutral-950 border border-neutral-800 space-y-4">
            <h2 class="text-sm font-bold uppercase tracking-wider text-primary flex items-center gap-2">
              <i data-lucide="activity" class="h-4 w-4"></i> Telemetri Server
            </h2>

            <div class="space-y-3 text-xs">
              <div class="flex justify-between py-2 border-b border-neutral-900">
                <span class="text-neutral-400">Host / Binding</span>
                <span class="font-mono text-white">{{ $status['hostname'] }}:{{ $status['java_port'] }}</span>
              </div>
              <div class="flex justify-between py-2 border-b border-neutral-900">
                <span class="text-neutral-400">RCON Host</span>
                <span class="font-mono text-white">{{ config('minecraft.rcon.host') }}:{{ config('minecraft.rcon.port') }}</span>
              </div>
              <div class="flex justify-between py-2 border-b border-neutral-900">
                <span class="text-neutral-400">Ping Delay</span>
                <span class="font-mono text-emerald-400">{{ $status['delay'] }} ms</span>
              </div>
              <div class="flex justify-between py-2 border-b border-neutral-900">
                <span class="text-neutral-400">Versi Engine</span>
                <span class="font-mono text-white">{{ $status['version'] }}</span>
              </div>
              <div class="flex justify-between py-2">
                <span class="text-neutral-400">Players Online</span>
                <span class="font-mono font-bold text-primary">{{ $status['players']['online'] }} / {{ $status['players']['max'] }}</span>
              </div>
            </div>
          </div>

          <!-- RCON Audit Logs -->
          <div class="p-6 rounded-2xl bg-neutral-950 border border-neutral-800 space-y-4">
            <h2 class="text-sm font-bold uppercase tracking-wider text-primary flex items-center gap-2">
              <i data-lucide="history" class="h-4 w-4"></i> Audit Log RCON
            </h2>

            <div class="space-y-2.5 max-h-[400px] overflow-y-auto pr-1">
              @forelse ($recentLogs as $log)
                <div class="p-3 rounded-xl bg-neutral-900/60 border border-neutral-900 text-xs space-y-1">
                  <div class="flex items-center justify-between text-[10px] text-neutral-500">
                    <span class="font-mono">{{ $log->created_at->format('H:i:s d/m') }}</span>
                    <span class="font-bold {{ $log->success ? 'text-emerald-400' : 'text-red-400' }}">{{ $log->success ? 'SUCCESS' : 'FAILED' }}</span>
                  </div>
                  <div class="font-mono text-white break-all">> {{ $log->command }}</div>
                  @if ($log->response)
                    <div class="text-[11px] text-neutral-400 font-mono break-all">{{ $log->response }}</div>
                  @endif
                </div>
              @empty
                <div class="py-6 text-center text-xs text-neutral-500">Belum ada riwayat command.</div>
              @endforelse
            </div>
          </div>

        </div>

      </div>

    </div>

    <!-- Hidden form for rank script -->
    <form id="rankForm" action="{{ route('admin.rcon.execute') }}" method="POST" style="display:none;">
      @csrf
      <input type="hidden" name="command" id="hidden_rank_cmd" />
    </form>

    <script>
      lucide.createIcons();

      function submitRankForm() {
        const player = document.getElementById('target_player').value.trim();
        const rank = document.getElementById('target_rank').value.trim();
        if (!player) {
          alert('Gamertag player tidak boleh kosong!');
          return;
        }
        document.getElementById('hidden_rank_cmd').value = 'lp user ' + player + ' parent set ' + rank;
        document.getElementById('rankForm').submit();
      }
    </script>
  </body>
</html>
