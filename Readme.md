# HitPay ![Logo](images/logo.png)

## Installing

== Method 1: ==
1. Download the latest released packaged from (https://github.com/hit-pay/opencart4-extension/releases) with extension as .ocmod.zip
2. Log in to admin
3. Navigate to Extensions => Installer
4. Browser your package and click upload button.
![](images/1.png)

== Method 2: ==

1. Download the latest released packaged from (https://github.com/hit-pay/opencart4-extension/releases)
2. Unzip the package.
3. And upload the files to under path/to/project/extension/hitpay/ folder via FTP.
4. This package not overwriting any core files.

== Install ==

1. If you followed either Method 1 or Method 2, you need to navigate Extensions => Extensions
2. Choose the Extension type as 'Payments'
![](images/2.png)
3. Scroll down to locate the HitPay Payment Gateway
![](images/3.png)
4. Click green colored + icon to install the extension.


## Upgradation

== Method 1: ==
1. Download the latest released packaged from (https://github.com/hit-pay/opencart4-extension/releases) with extension as .ocmod.zip
2. Log in to admin
3. Navigate to Extensions => Installer
4. Browser your package and click upload button. This would overwrite/replace the old extension files.
![](images/1.png)

== Method 2: ==

1. Download the latest released packaged from (https://github.com/hit-pay/opencart4-extension/releases)
2. Unzip the package.
3. And upload the files to under path/to/project/extension/hitpay/ folder via FTP.
4. This would overwrite/replace the old extension files.

## Configuration

1. Navigate Extensions => Extensions
2. Choose the Extension type as 'Payments'
![](images/2.png)
3. Scroll down to locate the HitPay Payment Gateway
![](images/3.png)
4. Click red colored - icon to edit the extension.
![](images/4.png)
5. Go to dashboard of hitpay:
![](images/5.png)
![](images/6.png)
6. Copy api key and salt from hitpay dashboard to opencart extension in the same named fields

## Using

1. Payment
![](images/7.png)
2. Checking orders
![](images/11.png)

## Refunds

1. Log in to Admin
2. Navigate to Sales ⇒ Orders
3. Click view icon link to navigate to Admin Order Details page.
4. If order is paid via 'HitPay Payment Gateway' and payment is successful and transaction id saved on the Database, then following screen will be displayed in the Admin Order view: (Scroll down to locate the HitPay Refund tab in the Order History section)
![](images/8.png)
5. Click blue colored 'Refund' Button, this will show the below screen:
![](images/9.png)
6. You can enter Full amount or Partial amount, but 0 or greater than total paid will not be allowed.
7. Click blue colored 'REFUND PAYMENT' button to initiate the Refund. If gateway accepted the refund request and the Refund details will be displayed like the below screen:
8. If full amount is refunded, then order status will be changed to 'Refunded' automatically.
9. Only one refund is allowed per transaction.
10. Refund is only applicable if the initial charge was made with PayNow or Card.
![](images/10.png)

## Changelog

= 1.0.0 =
* Initial release.
