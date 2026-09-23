<?php

declare(strict_types=1);
/**
 * @author Nicolas CARPi <nico-git@deltablot.email>
 * @copyright 2026 Nicolas CARPi
 * @see https://www.elabftw.net Official website
 * @license AGPL-3.0
 * @package elabftw
 */

namespace Elabftw\Elabftw;

use Elabftw\Enums\Messages;
use Elabftw\Exceptions\DatabaseErrorException;
use Elabftw\Exceptions\ImproperActionException;
use Elabftw\Exceptions\UnauthorizedException;
use Elabftw\Models\Config;
use Elabftw\Services\ScriptRunnerService;
use Exception;
use GuzzleHttp\Client;
use JsonException;
use Symfony\Component\HttpFoundation\JsonResponse;

use function dirname;
use function is_array;
use function json_decode;
use function _;

/**
 * Relay a script run request from an entity body to the configured script runner.
 * The script name comes from the data-script attribute of an element in the
 * body, and is only ever forwarded to the runner service, never executed here.
 */
require_once dirname(__DIR__) . '/init.inc.php';

$Response = new JsonResponse();
$Response->setData(array(
    'res' => true,
    'msg' => _('Saved'),
));

try {
    // decode JSON payload
    try {
        $reqBody = json_decode($App->Request->getContent(), true, 5, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        throw new ImproperActionException('Error decoding JSON payload');
    }
    if (!is_array($reqBody)) {
        throw new ImproperActionException('Invalid JSON payload');
    }
    $scriptName = (string) ($reqBody['name'] ?? '');
    if ($scriptName === '') {
        throw new ImproperActionException('Invalid or missing script name');
    }
    $args = is_array($reqBody['args'] ?? null) ? $reqBody['args'] : array();

    $configArr = Config::getConfig()->configArr;
    $Runner = new ScriptRunnerService(new Client(), (string) ($configArr['script_runner_url'] ?? ''));
    $output = $Runner->run($scriptName, $args);
    $Response->setData(array(
        'res' => true,
        'msg' => $output,
    ));
} catch (ImproperActionException | UnauthorizedException $e) {
    $Response->setData(array(
        'res' => false,
        'msg' => $e->getMessage(),
    ));
} catch (DatabaseErrorException $e) {
    $App->Log->error('', array(array('userid' => $App->Session->get('userid')), array('Error', $e)));
    $Response->setData(array(
        'res' => false,
        'msg' => $e->getMessage(),
    ));
} catch (Exception $e) {
    $App->Log->error('', array(array('userid' => $App->Session->get('userid')), array('exception' => $e)));
    $Response->setData(array(
        'res' => false,
        'msg' => Messages::GenericError->toHuman(),
    ));
} finally {
    $Response->send();
}
