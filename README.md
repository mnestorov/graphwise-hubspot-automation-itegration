# 📦 Graphwise - HubSpot Automation Integration

The **Graphwise - HubSpot Automation Integration** plugin is a powerful all-in-one solution that connects your **WordPress site** with **HubSpot** and other external systems for:

1. Displaying submitted user information on a **personalized Thank You page**
2. Tracking visitor interest in your WordPress **categories**
3. Receiving webhooks from an **Academy system**, updating contacts in HubSpot, and triggering a **Certificate API**

This plugin requires no coding skills. All you need is a WordPress website, a HubSpot account, and a few basic setup steps.

---

## 🌟 Plugin Features

### 1. Personalized Thank You Page

When a visitor fills out a HubSpot form on your WordPress site, they are redirected to a "Thank You" page where their **first name, last name, and email** are displayed dynamically.

### 2. Category Interest Tracking

The plugin keeps track of which content categories a visitor views. When the user fills out the HubSpot form, their **interest in different topics** is sent along using hidden fields.

This allows you to better understand what your leads care about the most — directly inside HubSpot!

### 3. Course Completion Integration (Webhook Support)

When a user completes a course in an external **Academy platform**, it sends a **webhook** to your WordPress site:
- The contact’s record is updated in HubSpot with the latest course completed.
- A **Certificate API** is triggered to issue their course completion certificate.

---

## 🛠️ How to Set It Up

### Prerequisites
- A working **WordPress website**
- Access to your **HubSpot account**
- A HubSpot **form with redirect** to a Thank You page
- Optionally: an external **Academy system** that can send webhooks

---

## 🔧 Set Up in WordPress

### 1. Install the Plugin

- **Log in to your WordPress admin dashboard**
- Go to **Plugins > Add New > Upload Plugin**
- Upload the plugin ZIP file: `gw-hubspot-automation-integration.zip`
- Click **Activate Plugin**

### 2. Create Categories (for interest tracking)

- **Go to Posts > Categories**
- Create at least 3 categories that match topics you write about, like:
   - email-marketing
   - crm-tools
   - automation-strategies

These category slugs (e.g., `email-marketing`) will be used as the keys to track visitor interests.

**Important:** Make sure your blog posts are assigned to these categories, so the plugin can track what users are reading.

### 3. Create the Thank You Page

- **Go to Pages > Add New**
- Title it: `Thank You`
- Leave the content empty – the plugin will insert a dynamic message after the form is submitted.
- Publish the page

### 4. Create the Contact Page (with HubSpot Form)

- **Go to Pages > Add New**
- Title it: `Contact`
- In the content editor, add:

```html
<script src="//js.hsforms.net/forms/v2.js"></script>
[hubspot_tracker_js]
```

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

## 🧪 How to Test the Course Completion Webhook (Scenario 3)

You can simulate a course completion webhook using Postman or any API testing tool.

### Step-by-Step Instructions
1. Open Postman or your preferred API client.
2. Set the method to POST.
3. Use this URL: `https://yourdomain.com/wp-json/graphwise/v1/course-complete` - Replace `yourdomain.com` with your WordPress site's domain. If you're using **Local by Flywheel**, make sure **Live Link is enabled** and use the public URL provided by Local.
4. Go to the Body tab, select `raw` and choose `JSON` format.
5. Paste the following sample JSON:
```json
{
  "email": "jane.doe@example.com",
  "course_name": "Advanced Automation Strategy"
}
```
6. Click Send.

### What Happens After Sending the Request?

**The plugin will:**

- Search for the contact in HubSpot by the provided email
- Update their profile with the latest_completed_course property
- Trigger a mock certificate API call

If HubSpot returns a match, the contact will be updated. The certificate API endpoint is a placeholder and should be replaced with your actual integration URL.

**Example Response:**
```json
{
  "status": "success"
}
```
