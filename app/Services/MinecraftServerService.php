<?php

namespace App\Services;

use DevLancer\MCPack\Ping;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class MinecraftServerService
{
    protected string $host;
    protected int $javaPort;
    protected int $bedrockPort;
    protected string $publicIp;

    public function __construct()
    {
        $this->host = config('minecraft.server.host', '172.18.0.2');
        $this->javaPort = (int) config('minecraft.server.java_port', 25565);
        $this->bedrockPort = (int) config('minecraft.server.bedrock_port', 19132);
        $this->publicIp = config('minecraft.server.public_ip', 'genzsmp.site');
    }

    /**
     * Get real-time server status with caching.
     *
     * @param bool $forceRefresh
     * @return array
     */
    public function getStatus(bool $forceRefresh = false): array
    {
        $cacheKey = 'minecraft_server_live_status';

        if ($forceRefresh) {
            Cache::forget($cacheKey);
        }

        return Cache::remember($cacheKey, 10, function () {
            return $this->queryLiveServer();
        });
    }

    /**
     * Query the live Minecraft server via dev-lancer/mc-pack status engine.
     *
     * @return array
     */
    protected function queryLiveServer(): array
    {
        $statusData = [
            'online' => false,
            'players' => [
                'online' => 0,
                'max' => 100,
                'list' => []
            ],
            'version' => config('minecraft.server.java_version', '1.20 - 1.26+'),
            'protocol' => null,
            'motd' => config('minecraft.server.tagline'),
            'delay' => 0,
            'hostname' => $this->publicIp,
            'java_port' => $this->javaPort,
            'bedrock_port' => $this->bedrockPort,
            'timestamp' => now()->toIso8601String()
        ];

        // 1. Try Querying Local Server using dev-lancer/mc-pack
        try {
            $startTime = microtime(true);
            $ping = new Ping($this->host, $this->javaPort, false, 2);

            if ($ping->connect()) {
                $info = $ping->getInfo();
                $delay = (int) round((microtime(true) - $startTime) * 1000);

                $statusData['online'] = true;
                $statusData['delay'] = $delay;

                if (isset($info['players'])) {
                    $statusData['players']['online'] = (int) ($info['players']['online'] ?? 0);
                    $statusData['players']['max'] = (int) ($info['players']['max'] ?? 100);

                    if (isset($info['players']['sample']) && is_array($info['players']['sample'])) {
                        $statusData['players']['list'] = array_map(fn($p) => $p['name'] ?? '', $info['players']['sample']);
                    }
                }

                if (isset($info['version'])) {
                    $statusData['version'] = $info['version']['name'] ?? $statusData['version'];
                    $statusData['protocol'] = $info['version']['protocol'] ?? null;
                }

                if (isset($info['description'])) {
                    $motd = $info['description'];
                    if (is_array($motd)) {
                        $statusData['motd'] = $motd['text'] ?? json_encode($motd);
                    } else {
                        $statusData['motd'] = (string) $motd;
                    }
                }

                return $statusData;
            }
        } catch (\Throwable $e) {
            Log::warning("Local dev-lancer/mc-pack Ping query failed ({$this->host}:{$this->javaPort}): " . $e->getMessage());
        }

        // 2. Fallback: Query Public API (mcsrvstat.us)
        try {
            $context = stream_context_create([
                'http' => ['timeout' => 3],
                'ssl' => ['verify_peer' => false, 'verify_peer_name' => false]
            ]);

            $url = "https://api.mcsrvstat.us/3/" . urlencode($this->publicIp);
            $response = @file_get_contents($url, false, $context);

            if ($response !== false) {
                $json = json_decode($response, true);
                if (!empty($json) && isset($json['online'])) {
                    $statusData['online'] = (bool) $json['online'];
                    if ($statusData['online']) {
                        $statusData['players']['online'] = $json['players']['online'] ?? 0;
                        $statusData['players']['max'] = $json['players']['max'] ?? 100;
                        $statusData['version'] = $json['version'] ?? $statusData['version'];
                        if (isset($json['players']['list'])) {
                            $statusData['players']['list'] = $json['players']['list'];
                        }
                    }
                }
            }
        } catch (\Throwable $ex) {
            Log::error("Public Minecraft API Fallback failed: " . $ex->getMessage());
        }

        return $statusData;
    }
}
