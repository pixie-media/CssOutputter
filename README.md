# PixieMedia_CssOutputter
Using this module, you can convert all your &lt;head&gt; stylesheets in to a blob of minified inline CSS.  For Hyva sites with very lightweight stylesheets, this can even further improve your Lighthouse page speed score by further reducing additional page requests.

Please note this module assumes all in-css requests to assets begin .. and replaces this with a relative path to your current themes static path.

Ie

.some-background { background:url(../images/bg.png) no-repeat center; }

Will become;

.some-background{background:url(/pub/static665544/frontend/[ThemeVendor]/[ThemeName]/[Locale]/images/bg.png) no-repeat center;}

This may not always be suitable for your needs.

Module is offered as is.  Feel free to suggest any improvements. 

## How to enable

Please ensure you also install the PixieMedia_Core menu.  

From the primary admin navigation, you will find the link to the configuration page in the Pixie Media menu item.
