console.log("HubSpot script loaded, initializing form...");
hbspt.forms.create({
    region: "eu1", // Adjust to your HubSpot region
    portalId: graphwiseHubSpotSettings.portalId,
    formId: graphwiseHubSpotSettings.formId,
    target: "#hubspot-form",
    onFormReady: function(form) {
        console.log("Form loaded successfully:", form);
        const visits = JSON.parse(localStorage.getItem('visited_categories') || '{}');
        for (const cat in visits) {
            const input = form.querySelector('input[name="interest_' + cat + '"]');
            if (input) {
                input.value = visits[cat];
            } else {
                const newInput = document.createElement('input');
                newInput.type = 'hidden';
                newInput.name = 'interest_' + cat;
                newInput.value = visits[cat];
                form.appendChild(newInput);
            }
        }
        console.log("Set hidden fields for categories:", visits);
    },
    onFormSubmit: function(form) {
        console.log("Form submission started (onFormSubmit triggered)");
    }
});

window.addEventListener('message', (event) => {
    console.log("Message event received:", event.data);
    if (
        event.data &&
        event.data.type === 'hsFormCallback' &&
        event.data.eventName === 'onFormSubmit'
    ) {
        console.log("✅ HubSpot onFormSubmit event captured:", event.data);
        const submissionData = event.data.data || [];
        console.log("Raw form data array:", submissionData);
        if (!submissionData || !Array.isArray(submissionData)) {
            console.error("No form data array found");
            return;
        }

        const formValues = {};
        submissionData.forEach(field => {
            if (field.name && field.value) {
                formValues[field.name] = field.value;
            }
        });
        console.log("Mapped form values:", formValues);

        const userData = {
            firstName: formValues['firstname'] || 'Unknown',
            lastName: formValues['lastname'] || 'User',
            email: formValues['email'] || 'No email'
        };
        console.log("Storing this data in sessionStorage:", userData);
        sessionStorage.setItem("hubspot_submitted_data", JSON.stringify(userData));
        console.log("Data stored in sessionStorage:", sessionStorage.getItem("hubspot_submitted_data"));
    } else if (event.data && event.data.type === 'hsFormCallback') {
        console.log("Other hsFormCallback event:", event.data.eventName);
    }
});