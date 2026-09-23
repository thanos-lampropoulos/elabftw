/**
 * @author Nicolas CARPi <nico-git@deltablot.email>
 * @copyright 2026 Nicolas CARPi
 * @see https://www.elabftw.net Official website
 * @license AGPL-3.0
 * @package elabftw
 */
import { entity } from './getEntity';
import { notify } from './notify';

// send the script name to the server which will relay it to the script runner service
function send(scriptName: string, args: object): Promise<void> {
  return fetch('app/controllers/RunScriptController.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
      'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
    },
    body: JSON.stringify({ name: scriptName, args: args }),
  }).then(resp => resp.json()).then(json => {
    notify.response(json);
  }).catch(error => notify.error(error));
}

/**
 * Bind the buttons with data-action="run-script" in the body of the entity.
 * The script name is set with data-script attribute and the current entity
 * is passed along, so the script can fetch its data through the API.
 */
export function runScript(): void {
  const bodyView = document.getElementById('body_view');
  if (!bodyView) return;
  bodyView.addEventListener('click', event => {
    const el = (event.target as HTMLElement).closest<HTMLElement>('[data-action="run-script"]');
    if (!el || !bodyView.contains(el)) return;
    const scriptName = el.dataset.script;
    if (!scriptName) {
      notify.error('script name missing');
      return;
    }
    // gather the values of the controls in the same container, so a script can
    // be triggered with the current user input
    const container = el.closest('fieldset, div, section') ?? bodyView;
    const args: Record<string, string> = {};
    container.querySelectorAll<HTMLInputElement>('input[type="text"]').forEach(input => {
      if (input.name) {
        args[input.name] = input.value;
      }
    });
    container.querySelectorAll<HTMLSelectElement>('select').forEach(select => {
      if (select.name) {
        args[select.name] = select.value;
      }
    });
    container.querySelectorAll<HTMLInputElement>('input[type="checkbox"], input[type="radio"]').forEach(input => {
      if (input.name && input.checked && input.value) {
        args[input.name] = input.value;
      }
    });
    send(scriptName, { entity: { type: entity.type, id: entity.id }, args: args });
  });
}
