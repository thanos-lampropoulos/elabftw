<?php

/**
 * @author Nicolas CARPi <nico-git@deltablot.email>
 * @copyright 2026 Nicolas CARPi
 * @see https://www.elabftw.net Official website
 * @license AGPL-3.0
 * @package elabftw
 */

declare(strict_types=1);

namespace Elabftw\Services;

use Elabftw\Exceptions\ImproperActionException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Exception\ConnectException;

use function is_array;
use function json_decode;
use function json_encode;
use function preg_match;
use function sprintf;
use function strlen;

/**
 * Relay a script execution request to an external runner service.
 * eLabFTW itself never executes user-provided script names: the runner
 * service is a separate program (e.g. a small Python HTTP server) which
 * has full control over which scripts it accepts and how it runs them.
 * The runner is expected to run on a private network or loopback
 * interface, not exposed to the internet.
 */
class ScriptRunnerService
{
    // only allow script names that look like a plain filename, no path traversal
    private const string NAME_REGEX = '/^[a-zA-Z0-9][a-zA-Z0-9_\-.]{0,127}$/';

    private const int REQUEST_TIMEOUT = 100;

    private const int MAX_ARGS_SIZE = 10000;

    private const int SUCCESS = 200;

    public function __construct(private Client $client, private string $runnerUrl = '') {}

    public function setRunnerUrl(string $runnerUrl): void
    {
        $this->runnerUrl = $runnerUrl;
    }

    /**
     * Send a run request to the runner service and return its output
     */
    public function run(string $scriptName, array $args = array()): string
    {
        if ($this->runnerUrl === '') {
            throw new ImproperActionException('No script runner configured. A sysadmin must set the runner URL in the Admin panel (sysconfig).');
        }
        if (strlen($scriptName) > 128 || !preg_match(self::NAME_REGEX, $scriptName)) {
            throw new ImproperActionException('Invalid script name.');
        }
        $bodyJson = json_encode(array('name' => $scriptName, 'args' => $args)) ?: '{}';
        if (strlen($bodyJson) > self::MAX_ARGS_SIZE) {
            throw new ImproperActionException('Script arguments are too big.');
        }
        try {
            $res = $this->client->post($this->runnerUrl, array(
                'timeout' => self::REQUEST_TIMEOUT,
                'headers' => array('Content-Type' => 'application/json'),
                'body' => $bodyJson,
            ));
        } catch (ConnectException $e) {
            throw new ImproperActionException(sprintf('Error connecting to script runner: %s', $this->runnerUrl), $e->getCode(), $e);
        } catch (BadResponseException $e) {
            throw new ImproperActionException(sprintf('Script runner returned an error (%d).', $e->getResponse()->getStatusCode()), $e->getCode(), $e);
        }
        if ($res->getStatusCode() !== self::SUCCESS) {
            throw new ImproperActionException(sprintf('Script runner returned an error (%d).', $res->getStatusCode()));
        }
        $body = json_decode($res->getBody()->getContents(), true);
        if (!is_array($body) || !isset($body['output'])) {
            throw new ImproperActionException('Unexpected response from script runner.');
        }
        return (string) $body['output'];
    }
}
