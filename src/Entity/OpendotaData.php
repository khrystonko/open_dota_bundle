<?php


namespace khrystonko\OpenDotaBundle\Entity;


class OpendotaData implements OpendotaInterface
{
    /** @var string $hostname Currently active API address (api.opendota.com by default) */
    private $hostname;

    /** @var bool $ready API interface status */
    private $ready = true;

    /** @var float $apiCooldown API cooldown time between requests */
    private $apiCooldown;

    /** @var float $lastRequest Last request time */
    private $lastRequest = 0;

    /** @var bool $reportStatus Verbose mode handler */
    private $reportStatus;

    /**
     * @param bool   $cliReportStatus = false Verbose mode flag
     * @param string $hostname        = '' URL of API instance. Uses public OpenDota instance by default.
     * @param int    $cooldown        = 0 API Cooldown 1000ms/200ms by default
     * @param string $apiKey          = '' OpenDota API Key
     */
    public function __construct(
        bool $cliReportStatus = false,
        string $hostname = null,
        int $cooldown = 0,
        string $apiKey = null
    ) {
        $this->hostname = $hostname ?? $_ENV['OPEN_DOTA_API'];
        $this->apiKey = $apiKey;

        if ($cooldown) {
            $this->apiCooldown = $cooldown / 1000;
        } elseif (!$this->apiKey) {
            $this->apiCooldown = 0.25;
        } else {
            $this->apiCooldown = 1;
        }

        $this->reportStatus = $cliReportStatus;

        if ($this->reportStatus) {
            echo "[I] Initialised OpenDota instance.\n[ ] \tHost: " . $this->hostname . "\n";
        }
    }

    /**
     * GET /heroes
     * Get hero data
     *
     * @param int $mode = 0 Fast mode flag (skip requests if cooldown or wait for API)
     *
     * @return mixed $result
     */
    public function heroes(int $mode = 0)
    {
        return $this->request('heroes', $mode);
    }

    /**
     * GET /heroes/{hero_id}/matches
     * Get recent matches with a hero
     *
     * @param int $hero_id {hero_id}
     * @param int $mode    = 0 Fast mode flag (skip requests if cooldown or wait for API)
     *
     * @return mixed $result
     */
    public function heroMatches(int $hero_id, int $mode = 0)
    {
        return $this->request("heroes/{$hero_id}/matches", $mode);
    }

    /**
     * GET /matches/{match_id}
     * Returns match data
     *
     * @param int $match_id {match_id}
     * @param int $mode     = 0
     *
     * @return mixed $result Match data blob
     */
    public function match(int $match_id, int $mode = 0)
    {
        return $this->request("matches/{$match_id}", $mode);
    }

    /**
     * Execute GET request
     *
     * @param string $url
     * @param array $data
     * @return bool|string $response
     */
    private function get(string $url, array $data = [])
    {
        if (!$this->apiKey) {
            $data['api_key'] = $this->apiKey;
        }

        if (!empty($data)) {
            $url .= '?' . http_build_query($data);
        }

        $curl = curl_init($this->hostname . $url);

        if ($this->reportStatus) {
            echo '...';
        }

        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, \false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, \true);

        $response = curl_exec($curl);

        if (curl_errno($curl)) {
            $response = \false;
        }

        if ($this->reportStatus) {
            if (!curl_errno($curl)) {
                echo "OK\n";
            } else {
                echo "\n[E] cURL error: " . curl_error($curl) . "\n";
            }
        }

        curl_close($curl);

        return $response;
    }

    /**
     * Execute POST request
     *
     * @param string $url
     * @param array $data
     * @return string $response
     */
    private function post(string $url, array $data = [])
    {
        if (!$this->apiKey) {
            $url .= '?api_key=' . $this->apiKey;
        }

        $curl = curl_init($this->hostname . $url);

        if ($this->reportStatus) {
            echo '...';
        }

        curl_setopt($curl, CURLOPT_POST, \true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, \http_build_query($data));
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, \false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, \true);

        $response = curl_exec($curl);

        if (curl_errno($curl)) {
            $response = false;
        }

        if (mb_strpos($response, '<!DOCTYPE HTML>') !== false) {
            $response = '{"error":"Node disabled"}';
        }

        if ($this->reportStatus) {
            if (curl_errno($curl) || !$response) {
                echo "\n[E] cURL error: " . curl_error($curl) . "\n";
            } else {
                echo "OK\n";
            }
        }

        curl_close($curl);

        return $response;
    }

    /**
     * Handles API cooldown
     */
    private function cooldown()
    {
        if (($ms_timestamp = \microtime(true)) - $this->lastRequest < $this->apiCooldown) {
            if ($this->reportStatus) {
                echo '...Holding On';
            }

            usleep((int) (($ms_timestamp - $this->lastRequest) * 1000000));
        }

        $this->ready = true;
    }

    /**
     * Handles last request time
     */
    private function setLastRequest()
    {
        $this->lastRequest = microtime(true);
        $this->ready = false;
    }

    /**
     * Handles requests and resulting data
     *
     * @param string $url
     * @param int    $mode
     * @param mixed  $data
     * @param bool   $post
     *
     * @return mixed $result
     */
    private function request($url, $mode, $data = [], $post = \false)
    {
        if ($this->reportStatus) {
            echo "[ ] Sending request to /{$url} endpoint";
        }

        if ($mode == 0) {
            $this->cooldown();
        } elseif ($mode == -1) {
            if (!$this->ready) {
                if ($this->reportStatus) {
                    echo "[E] API Cooldown. Skipping request\n";
                }
            }
        }

        $result = $post ? $this->post($url, $data) : $this->get($url, $data);

        $this->setLastRequest();

        $result = json_decode($result, true);

        if (isset($result['error']) || empty($result)) {
            if ($mode == -1) {
                if ($this->reportStatus) {
                    echo '[E] ' . $result['error'] . ". Skipping request\n";
                }

                return false;
            }

            if ($result['error'] == 'Not Found') {
                if ($this->reportStatus) {
                    echo "[ ] 404, Skipping\n";
                }

                return false;
            }

            if ($result['error'] == 'Node disabled') {
                if ($this->reportStatus) {
                    echo "[ ] Node disabled\n";
                }

                return \false;
            }

            if ($mode == 0) {
                if ($this->reportStatus) {
                    echo '[ ] ' . $result['error'] . ". Waiting\n";
                }

                sleep(1);

                return $this->request($url, $mode, $data, $post);
            }
        } else {
            return $result;
        }
    }
}