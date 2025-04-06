// Category tracking for single posts and category archives
if (typeof graphwiseCategories !== 'undefined' && graphwiseCategories.currentCategories) {
    const visitedCategories = JSON.parse(localStorage.getItem('visited_categories') || '{}');
    const currentCategories = graphwiseCategories.currentCategories;
    currentCategories.forEach(cat => {
        visitedCategories[cat] = (visitedCategories[cat] || 0) + 1;
    });
    localStorage.setItem('visited_categories', JSON.stringify(visitedCategories));
    console.log("Updated visited categories:", visitedCategories);
}

// Thank You page logic
if (document.getElementById('thank-you-message')) {
    document.addEventListener("DOMContentLoaded", function() {
        const data = sessionStorage.getItem("hubspot_submitted_data");
        console.log("Retrieved data on Thank You page:", data);
        if (data) {
            try {
                const { firstName, lastName, email } = JSON.parse(data);
                document.getElementById("thank-you-message").innerHTML = `
                    <h2>Thank you, ${firstName} ${lastName}!</h2>
                    <p>Confirmation sent to: <strong>${email}</strong>.</p>
                `;
                sessionStorage.removeItem("hubspot_submitted_data");
            } catch (e) {
                console.error("Error parsing sessionStorage data:", e);
            }
        } else {
            document.getElementById("thank-you-message").innerHTML = `
                <h2>Thank you!</h2>
                <p>No submission data found.</p>
            `;
        }
    });
}