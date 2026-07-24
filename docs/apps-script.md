# Google Sheet export — Apps Script setup

Open Form Builder exports each submission by POSTing JSON to a Google Apps Script
web app you deploy. No service account, no OAuth, no API keys.

## 1. Create the sheet + script

1. Create a Google Sheet. Note its tab name (default `Sheet1`).
2. **Extensions → Apps Script**. Replace the contents with:

```javascript
// Open Form Builder — Sheet export endpoint.
function doPost(e) {
  try {
    var data = JSON.parse(e.postData.contents);
    var sheet = SpreadsheetApp.getActiveSpreadsheet().getSheetByName('Sheet1');

    // Build a header row from the field keys the first time we see them.
    var fields = data.fields || {};
    var keys = Object.keys(fields);

    if (sheet.getLastRow() === 0) {
      sheet.appendRow(
        ['Submitted', 'Submission ID', 'Form ID', 'Status', 'Amount', 'Currency'].concat(keys)
      );
    }

    var row = [
      data.submitted_at || new Date(),
      data.submission_id || '',
      data.form_id || '',
      data.payment_status || '',
      data.amount || '',
      data.currency || ''
    ];
    keys.forEach(function (k) { row.push(fields[k]); });
    sheet.appendRow(row);

    return ContentService
      .createTextOutput(JSON.stringify({ ok: true }))
      .setMimeType(ContentService.MimeType.JSON);
  } catch (err) {
    return ContentService
      .createTextOutput(JSON.stringify({ ok: false, error: String(err) }))
      .setMimeType(ContentService.MimeType.JSON);
  }
}
```

## 2. Deploy

1. **Deploy → New deployment → Web app**.
2. *Execute as*: **Me**.
3. *Who has access*: **Anyone**.
4. Deploy, authorize, and copy the **Web app URL** (ends in `/exec`).

## 3. Connect

Paste that URL into your form's **Settings → Google Sheet export → Apps Script
web-app URL**, and enable the toggle.

### Notes

- The export is fire-and-forget and runs after a submission is recorded (and,
  for paid forms, after payment succeeds). A slow or failing sheet never blocks
  the visitor.
- Column order follows the field keys; new fields append new columns only if you
  clear the header row, or you can manage headers manually.
