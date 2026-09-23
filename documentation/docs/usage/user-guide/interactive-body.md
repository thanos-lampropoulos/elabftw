---
sidebar_position: 8
title: Interactive experiment bodies
---

# Interactive experiment bodies

The Main Text of an entry can contain interactive controls: dropdown menus (`<select>`), radio buttons, checkboxes, text inputs and buttons. This is useful to structure the information of an experiment: ask a question, offer options, and let the user pick one. The selected values are part of the body of the experiment, so they are saved with it and appear in exports and revisions.

## Adding controls to the Main Text

Switch the editor to the code view (Toolbar → `Code view` or the `</>` button) and add the controls directly in the HTML. Example:

```html
<p>Which solvent was used?</p>
<select name="solvent">
  <option value="acetone">Acetone</option>
  <option value="ethanol" selected>Selected: Ethanol</option>
  <option value="water">Water</option>
</select>

<p>Was the sample heated?</p>
<label><input type="radio" name="heated" value="yes"> Yes</label>
<label><input type="radio" name="heated" value="no" checked> No</label>
```

When the entry is viewed (not edited), changing a control saves the new value in the database immediately: nothing is lost when the page is closed. Users without write access to the entry can use the controls but their changes are not saved.

When the entry is edited, the controls in the editor are active too, and their state is saved along with the body when the entry is saved.

:::note
The controls must have a `name` attribute for their value to be persisted. The values are saved as attributes in the HTML (`checked`, `selected`, `value`), so they are visible in the code view and survive exports.
:::

## Triggering scripts from the body

A button in the Main Text can trigger the execution of a script on a machine of your choice. eLabFTW does not execute the script itself: it sends a request to a **script runner service**, a small program that you run next to eLabFTW and that you fully control. An example runner written in Python is provided in `src/tools/script-runner.py`.

:::warning Security
The script runner executes scripts. Only run it on a machine you control, keep it on the loopback interface (`127.0.0.1`) and think hard about what the scripts do. A sysadmin must set the runner URL, and it should never be exposed to the internet.
:::

### Setting it up

1. A sysadmin sets the **URL of the script runner service** in Admin → Server settings.
2. The runner service is started on that machine (see the header of `src/tools/script-runner.py`), with the scripts to expose in its `scripts/` directory.
3. A user adds a button in the Main Text (code view):

```html
<button type="button" data-action="run-script" data-script="analyze.py">Run analysis</button>
```

When the button is clicked, eLabFTW sends the script name, the current values of the controls of the containing block and the entity type and id to the runner. The runner executes the script and the output is displayed to the user. The script can use the entity id to fetch the full entry through the [API](https://doc.elabftw.net/api.html) if it needs more data.

### Example script

A script receives a JSON object on its standard input with the `args` collected from the controls and the `entity` that triggered it. Example `analyze.py`:

```python
#!/usr/bin/env python3
import json
import sys

payload = json.load(sys.stdin)
print(f"Analyzing entity {payload['entity']['type']} #{payload['entity']['id']}")
for key, value in payload["args"].items():
    print(f"{key}: {value}")
```
