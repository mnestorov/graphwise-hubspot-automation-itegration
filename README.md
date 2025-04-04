# 📦 Graphwise - HubSpot Automation Integration

The **Graphwise - HubSpot Automation Integration** plugin is a powerful all-in-one solution that connects your **WordPress site** with **HubSpot** and other external systems for:

1. ✅ Displaying submitted user information on a **personalized Thank You page**
2. ✅ Tracking visitor interest in your WordPress **categories**
3. ✅ Receiving webhooks from an **Academy system**, updating contacts in HubSpot, and triggering a **Certificate API**

This plugin requires no coding skills. All you need is a WordPress website, a HubSpot account, and a few basic setup steps.

---

## 🌟 Plugin Features

### ✅ 1. Personalized Thank You Page
When a visitor fills out a HubSpot form on your WordPress site, they are redirected to a "Thank You" page where their **first name, last name, and email** are displayed dynamically.

### ✅ 2. Category Interest Tracking
The plugin keeps track of which content categories a visitor views. When the user fills out the HubSpot form, their **interest in different topics** is sent along using hidden fields.

This allows you to better understand what your leads care about the most — directly inside HubSpot!

### ✅ 3. Course Completion Integration (Webhook Support)
When a user completes a course in an external **Academy platform**, it sends a **webhook** to your WordPress site:
- The contact’s record is updated in HubSpot with the latest course completed.
- A **Certificate API** is triggered to issue their course completion certificate.

---

## 🛠️ How to Set It Up

### 🔧 Prerequisites
- A working **WordPress website**
- Access to your **HubSpot account**
- A HubSpot **form with redirect** to a Thank You page
- Optionally: an external **Academy system** that can send webhooks

---

## 🚀 Installation (WordPress Side)

1. **Log into your WordPress admin dashboard**
2. Go to **Plugins > Add New > Upload Plugin**
3. Upload the ZIP file: `gw-hubspot-automation-integration.zip`
4. Click **Activate Plugin**

---

## ⚙️ Configure the Plugin Settings

1. Go to **Settings > Graphwise**
2. Fill in the following:

   | Field               | What to Enter                                                                 |
   |--------------------|-------------------------------------------------------------------------------|
   | **HubSpot Token**   | Your HubSpot Private App token. Generate it in HubSpot → Settings → Integrations |
   | **Portal ID**       | Your HubSpot portal ID (find in form embed code)                             |
   | **Form ID**         | Your HubSpot form ID (find in form embed code)                               |

3. Click **Save Changes**

---

## ✍️ Create a HubSpot Form

1. In your **HubSpot dashboard**, go to:
   **Marketing > Lead Capture > Forms**
2. Create a **New Form** with fields like:
   - First Name (name: `firstname`)
   - Last Name (name: `lastname`)
   - Email (name: `email`)
3. Under **Options**, set **Redirect to another page** and enter the URL of your WordPress **Thank You** page (e.g., `/thank-you`)
4. Click **Publish**
5. Go to **Share > Embed**, and find your:
   - `portalId`
   - `formId`
6. Paste both values into your WordPress plugin settings

---

## 📄 Add the Form to a Page in WordPress

1. Create a WordPress Page (e.g., "Contact Us")
2. Paste the following inside the content editor:

```html
<script src="//js.hsforms.net/forms/v2.js"></script>
[hubspot_tracker_js]
