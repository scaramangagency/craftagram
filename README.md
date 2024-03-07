# craftagram plugin for Craft CMS 4.x

Grab Instagram content through the Instagram Basic Display API

## Requirements

This plugin requires Craft CMS 4.0.0 or later.

## Installation

To install the plugin, follow these instructions.

1. Open your terminal and go to your Craft project:

        cd /path/to/project

2. Then tell Composer to load the plugin:

        composer require scaramangagency/craftagram

3. In the Control Panel, go to Settings → Plugins and click the “Install” button for craftagram.

## Setting up your Facebook App

> :warning: Important note on step 6 – your valid OAuth Redirect URI **has** to be the URL for the base site, do not try to use individual multi-site URLs. The base site URL will be appended with a state parameter ensuring that the correct site is targeted on the response from Instagram

1. Go to https://developers.facebook.com, click My Apps, and create a new app. Select _Consumer_ as your app type, and fill out the required information.
2. Once you have created the app and are in the App Dashboard, navigate to Settings > Basic, scroll the bottom of page, and click Add Platform.
3. Choose Website, add your website’s URL, and save your changes.
4. Click 'Add Product' on the left hand side menu, locate the Instagram Basic Display product, and click Set Up to add it to your app.
5. Click Basic Display under Products > Instagram in the sidebar, scroll to the bottom of the page, then click Create New App, and name your app whatever you like.
6. When presented with the app page, complete each section using the below:
    - **Valid OAuth Redirect URIs** Enter your _Primary Site base URL_, appended with `/actions/craftagram/default/auth` (i.e. https://www.yourwebsite.com/actions/craftagram/default/auth).
    - **Deauthorize Callback URL** and **Data Deletion Request Callback URL** Use the same URL as above.
    - Ignore **App Review**, as we do not recommend that you publish your app. You can use the app indefinitely in development mode.
    - Save Changes
7. Navigate to Roles > Roles and scroll down to the Instagram Testers section. Click Add Instagram Testers and enter the name of the Instagram account you're linking up.
8. Open a new web browser and go to www.instagram.com and sign into your Instagram account that you just invited. Navigate to (Profile Icon) > Settings > Apps and Websites > Tester Invites and accept the invitation.

That's it! You won't need any extra setup now. What you will need to do is go to Products > Instagram > Basic Display and scroll down to `Instagram App ID
` and `Instagram App Secret`, as you'll need to add these in the next step.

## Configuring craftagram

Go to the settings page for `craftagram` and enter your `App ID` and `App Secret` from the step above into the required boxes, and hit 'Save'. When the page refreshes, you'll see there's a new button `Authorise Craft`. Click that button to go to instagram to complete the authorisation procedure.

> Tip: The App ID and App Secret settings can be set to environment variables. See Environmental Configuration in the Craft docs to learn more about that.

Instagram may challenge you with a login screen, so handle that, then click 'Authorize'. You will be redirected back to Craft with the Long Access Token field populated.

> :warning: Check you're logged in to the correct account before you try to authenticate (or don't be logged in at all). If you're logged in with a different user in the current browser session, you're going to have issues.

### Keeping your token active

Instagram tokens expire in 60 days, so you'll need to set up a cron job to keep the token alive. The refresh action is `actions/craftagram/default/refresh-token`.

For example, this would run the token refresh every month, for all enabled sites with tokens

```
0 0 1 * * /usr/bin/wget -q https://www.yourwebsite.com/actions/craftagram/default/refresh-token >/dev/null 2>&1
```

If you just want to update a single site you can add the optional param `siteId`

```
0 0 1 * * /usr/bin/wget -q https://www.yourwebsite.com/actions/craftagram/default/refresh-token?siteId=<your siteId> >/dev/null 2>&1
```

If you fail to set up the cron, you can still refresh the token manaully, by going to the settings page, clicking the `Authorise Craft` and following the steps outlined above.

> :warning: You cannot refresh access tokens for private Instagram accounts, so ensure the account used in your tester invite above is public

## Using craftagram

Using the plugin is pretty simple

```
{% set craftagram = craft.craftagram.getInstagramFeed() %}

{% if craftagram|length %}
    {% for item in craftagram.data %}
        <img src={{item.media_url}} />
    {% endfor %}
{% endif %}
```

There are two parameters available to the variable, `limit` and `siteId`. The default limit from instagram is 25.

```
{% set craftagram = craft.craftagram.getInstagramFeed(25, currentSite.id) %}
```

| Field Name | Description |
| --- | --- |
| limit | The default limit from instagram is 25 |
| siteId | The current site's ID. If you only have one site on your install you can leave this blank, otherwise pass the `siteId` for the site you have added the authorisation to. You can hard-code the site ID if you have only set up authorisation on one of your multi-site installs, otherwise pass the current `siteId` dynamically |

The options that you get are [all of the options](https://developers.facebook.com/docs/instagram-basic-display-api/reference/media#fields) provided from the API endpoint. For brevity, they are:

| Field Name | Description |
| --- | --- |
| caption | The Media's caption text. Not returnable for Media in albums. |
| id | The Media's ID. |
| media_type | The Media's type. Can be IMAGE, VIDEO, or CAROUSEL_ALBUM. |
| media_url | The Media's URL. |
| permalink | The Media's permanent URL. |
| thumbnail_url | The Media's thumbnail image URL. Only available on VIDEO Media. |
| timestamp | The Media's publish date in ISO 8601 format. |
| username | The Media owner's username. |

### Pagination

If you have limits on your feed, you can pass the `after` (or `before` if you're paginating backwards) parameter and init an AJAX function to return the data.

Remember to pass `limit`, and also to pass the correct `siteId` for the active site, if appropriate.

For example, you could do this to have a 'load more' button:
```
{% set craftagram = craft.craftagram.getInstagramFeed(10) %}

{% if craftagram|length %}
    <div data-js="insta-wrapper">
        {% for item in craftagram.data %}
            <img src={{item.media_url}} />
        {% endfor %}
    </div>

    <a data-after="{{ craftagram.paging.cursors.after }}" data-js="load-more">Load more</a>
{% endif %}

{% js %}
    $("[data-js=load-more]").click(function(e) {
        e.preventDefault();
        $.get("{{ parseEnv(craft.app.sites.primarySite.baseUrl) }}/actions/craftagram/default/get-next-page?siteId={{ currentSite.id }}&limit=10&url=" + $(this).data('after'), function(res) {
            data = $.parseJSON(res);

            // For each, append the item to our wrapper
            $.each(data["data"], function() {
                $("[data-js='insta-wrapper']").append("<img src="+$(this)[0]["media_url"]+" />");
            });

            // Update the paging with the next after.
            $("[data-js=load-more]").data("after", data["paging"]["cursors"]["after"]);
        });
    });
{% endjs %}
```

### Headless mode

If you're using Craft headless (or generally just need a JSON formatted version of your results), you can access the instagram feed via `/actions/craftagram/default/api` (or `/craftagramApi` if you want to save some bytes), which will return the raw JSON data from instagram. You can pass the following parameters:

| URL Parameter | Description |
| --- | --- |
| limit | The default limit from instagram is 25 |
| siteId | The current site's ID. If you only have one site on your install you can leave this blank, otherwise pass the `siteId` for the site you have added the authorisation to. You can hard-code the site ID if you have only set up authorisation on one of your multi-site installs, otherwise pass the current `siteId` dynamically |
| url | Pass the `after` or `before` parameters from `data->paging->cursors` to get the next or the previous set of results |

#### Security

There is a setting to opt-in to a more secure API endpoint. If you switch it on, you must pass a `Basic Auth` header to access this endpoint, otherwise you will receive an error. The Username and Password should be for an activated Craft user. **Please note** that you must enable the secure endpoint for each site individually.

### Profile Information

> :warning: :warning: :warning:
>
> This uses the publically available instagram GraphQL API, accessible by adding *?__a=1* to an instagram URL. **The API endpoint is no longer functional, so do not use this feature.** It will be deprecated in a future release.

Used to grab some basic profile information not available natively in the Basic Display API. You can pass in any instagram profile, as this endpoint returns this information regardless of public or private status.

```
{% set craftagram = craft.craftagram.getProfileMeta("scaramanga_agency") %}
```

This variable has 4 available fields:

| Field Name |
| --- |
| profile_picture |
| profile_picture_hd |
| followers |
| following |

### Rate Limits

Be conscious you might be subject to rate limits from instagram, so if you're on a high traffic website you might get rate limited. You can read more about rate limits at instagram's [documentation](https://developers.facebook.com/docs/graph-api/overview/rate-limiting#instagram). 

### Media Size

The image returned from the API is an immutable size–it used to be you could use modifiers like `large` to get an image at a certain size, but no more. You will need to use a plugin that supports transforming images from remote URL's to resize the images returned from Instagram.


---
Brought to you by [Scaramanga Agency](https://scaramanga.agency)
