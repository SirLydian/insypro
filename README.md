# Insypro

This app is developed to parse a template PDF to JSON.

Demo: http://insypro.pauwelsruben.be/

After uploading the PDF (Dutch / French) the system parses data
from the table inside the PDF and returns it as a JSON object.
If you wish to download the JSON object you can select 'Download Data Only'.

The system looks up the company vat number and retrieves the company name
and company address thanks to the VIES VAT API found here:
https://www.programmableweb.com/api/vies-vat
