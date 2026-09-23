/**
 * @author Nicolas CARPi <nico-git@deltablot.email>
 * @copyright 2026 Nicolas CARPi
 * @see https://www.elabftw.net Official website
 * @license AGPL-3.0
 * @package elabftw
 */
import { ApiC } from './api';
import { entity } from './getEntity';
import { notify } from './notify';

// A pristine copy of the body as saved in database, used to persist
// the state of the interactive controls (select, radio, checkbox, text)
// without the live modifications (MathJax output and such).
let pristineBody: string = '';

// fetch the body as it is in the database, without any client side modification
async function fetchPristineBody(): Promise<string> {
  const json = await ApiC.getJson(`${entity.type}/${entity.id}`);
  return json.body as string;
}

// update the pristine copy of the body with the state of the changed control
function updatePristineBody(changed: HTMLInputElement | HTMLSelectElement): void {
  const container = document.createElement('div');
  container.innerHTML = pristineBody;
  if (changed instanceof HTMLInputElement) {
    const input = Array.from(container.querySelectorAll<HTMLInputElement>(`input[name="${changed.name}"]`))
      .find(i => i.value === changed.value);
    if (!input) return;
    if (changed.type === 'checkbox' || changed.type === 'radio') {
      if (changed.checked) {
        input.setAttribute('checked', 'checked');
      } else {
        input.removeAttribute('checked');
      }
      return;
    }
    input.setAttribute('value', changed.value);
    return;
  }
  const select = container.querySelector(`select[name="${changed.name}"]`) as HTMLSelectElement | null;
  if (!select) return;
  Array.from(select.options).forEach(option => {
    if (option.value === changed.value) {
      option.setAttribute('selected', 'selected');
    } else {
      option.removeAttribute('selected');
    }
  });
}

// save the pristine body in the database
function persistBody(): void {
  ApiC.patch(`${entity.type}/${entity.id}`, { body: pristineBody, notifOnSaved: 0 })
    .catch(error => notify.error(error));
}

/**
 * Persist the interactive controls of the body: when a select, radio,
 * checkbox or text input of the Main Text is changed in view mode,
 * the change is saved in the database so it is not lost.
 */
export function bindBodyControls(): void {
  const bodyView = document.getElementById('body_view');
  if (!bodyView) return;
  // only users with write access can persist changes
  if (bodyView.dataset.writable !== '1') return;
  fetchPristineBody().then(body => {
    pristineBody = body;
  }).catch(error => notify.error(error));
  bodyView.addEventListener('change', event => {
    const target = event.target as HTMLInputElement | HTMLSelectElement;
    if (!target || !target.name) return;
    if (target instanceof HTMLInputElement && !['radio', 'checkbox', 'text'].includes(target.type)) return;
    if (pristineBody === '') return;
    updatePristineBody(target);
    persistBody();
  });
}
