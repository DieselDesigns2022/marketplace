<script>
(() => {
    const states = {
        ALABAMA:"AL",ALASKA:"AK",ARIZONA:"AZ",ARKANSAS:"AR",
        CALIFORNIA:"CA",COLORADO:"CO",CONNECTICUT:"CT",
        DELAWARE:"DE",FLORIDA:"FL",GEORGIA:"GA",HAWAII:"HI",
        IDAHO:"ID",ILLINOIS:"IL",INDIANA:"IN",IOWA:"IA",
        KANSAS:"KS",KENTUCKY:"KY",LOUISIANA:"LA",MAINE:"ME",
        MARYLAND:"MD",MASSACHUSETTS:"MA",MICHIGAN:"MI",
        MINNESOTA:"MN",MISSISSIPPI:"MS",MISSOURI:"MO",
        MONTANA:"MT",NEBRASKA:"NE",NEVADA:"NV",
        "NEW HAMPSHIRE":"NH","NEW JERSEY":"NJ",
        "NEW MEXICO":"NM","NEW YORK":"NY",
        "NORTH CAROLINA":"NC","NORTH DAKOTA":"ND",
        OHIO:"OH",OKLAHOMA:"OK",OREGON:"OR",
        PENNSYLVANIA:"PA","RHODE ISLAND":"RI",
        "SOUTH CAROLINA":"SC","SOUTH DAKOTA":"SD",
        TENNESSEE:"TN",TEXAS:"TX",UTAH:"UT",
        VERMONT:"VT",VIRGINIA:"VA",WASHINGTON:"WA",
        "WEST VIRGINIA":"WV",WISCONSIN:"WI",WYOMING:"WY",
        "DISTRICT OF COLUMBIA":"DC",
        "PUERTO RICO":"PR",GUAM:"GU",
        "AMERICAN SAMOA":"AS",
        "NORTHERN MARIANA ISLANDS":"MP",
        "U.S. VIRGIN ISLANDS":"VI",
        "US VIRGIN ISLANDS":"VI"
    };

    const validCodes = new Set(Object.values(states));

    document.querySelectorAll('form').forEach(form => {
        const state = form.querySelector('[name="billing_state"]');
        const zip = form.querySelector('[name="billing_postal_code"]');
        const country = form.querySelector('[name="billing_country"]');

        if (!state || !zip || !country) return;

        function normalize() {
            const us = country.value.toUpperCase() === 'US';

            state.setCustomValidity('');
            zip.setCustomValidity('');

            if (!us) {
                state.placeholder = 'State / Province / Region';
                return;
            }

            state.placeholder = 'MI or Michigan';
            state.required = true;

            const entered = state.value.trim().toUpperCase()
                .replace(/\s+/g, ' ');

            const code = states[entered] || entered;

            if (validCodes.has(code)) {
                state.value = code;
            } else if (entered) {
                state.setCustomValidity(
                    'Enter a valid US state, such as MI or Michigan.'
                );
            }

            zip.value = zip.value.trim();

            if (zip.value && !/^\d{5}(-\d{4})?$/.test(zip.value)) {
                zip.setCustomValidity(
                    'Enter a 5-digit ZIP code, such as 48239.'
                );
            }
        }

        function clearStateError() {
            state.setCustomValidity('');
        }

        function clearZipError() {
            zip.setCustomValidity('');
        }

        state.addEventListener('input', clearStateError);
        zip.addEventListener('input', clearZipError);
        state.addEventListener('change', normalize);
        zip.addEventListener('change', normalize);
        country.addEventListener('change', normalize);

        form.addEventListener('submit', event => {
            normalize();

            if (!state.checkValidity() || !zip.checkValidity()) {
                event.preventDefault();

                if (!state.checkValidity()) {
                    state.reportValidity();
                } else {
                    zip.reportValidity();
                }
            }
        });

        if (country.value.toUpperCase() === 'US') {
            state.placeholder = 'MI or Michigan';
        }
    });
})();
</script>
