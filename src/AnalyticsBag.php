<?php

namespace Spatie\AnalyticsTracker;

use Illuminate\Contracts\Session\Session;
use Illuminate\Http\Request;

class AnalyticsBag
{
    protected Session $session;

    /** @var array[]|string[][] */
    protected array $trackedParameters;

    protected string $sessionKey;

    public function __construct(Session $session, array $trackedParameters, string $sessionKey)
    {
        $this->session = $session;
        $this->trackedParameters = $trackedParameters;
        $this->sessionKey = $sessionKey;
    }

    public function putFromRequest(Request $request)
    {
        $parameters = $this->determineFromRequest($request);

        $referer = request()->headers->get('referer');
        $parse = parse_url($referer);

        $currentBag = $this->session->get($this->sessionKey);

        $existing = false;
        if(isset($currentBag) && count($currentBag) != 0) {
            foreach ($currentBag as $bag) {
                unset($bag['datetime'], $bag['referer'], $bag['domain']);

                if ($bag == $parameters) {
                    $existing = true;
                }
            }
        }
        
        if(!$existing && count($parameters)>1) {
            // Lets timestamp the request.
            $parameters['datetime'] = (String) \Carbon\Carbon::now();
            $parameters['referer'] = $referer ?? null;
            $parameters['domain'] = $parse['host'] ?? null;

            $currentBag[] = $parameters;

            $this->session->put($this->sessionKey, $currentBag);
        }
    }

    public function get(): array
    {
        return $this->session->get($this->sessionKey, []);
    }

    protected function determineFromRequest(Request $request): array
    {
        return collect($this->trackedParameters)
            ->mapWithKeys(function ($trackedParameter) use ($request) {
                $source = new $trackedParameter['source']($request);

                return [$trackedParameter['key'] => $source->get($trackedParameter['key'])];
            })
            ->filter()
            ->toArray();
    }
}
