# Transarmor Integration

How the TransArmor Solution Works within Payeezy Gateway

1. Payment card information is keyed into merchant’s payment page (Hosted Checkout or Web Service API)
2. Personal Account Number (PAN) and expiration date are sent to the Payeezy Gateway.
3. Payeezy Gateway builds a message to the switch that includes the data elements captured by the merchant.
4. Card number is passed to bank for authorization using SSL encryption and also to First Data for tokenization
5. Authorization and token number are returned to the merchant
6. Token number replaces card number in all subsequent instances and activities such as analytics, marketing, etc.
7. Settlement, adjustments, refunds, chargebacks and other activities are performed using the token number in place of the card number


![alt text](https://support.payeezy.com/hc/en-us/article_attachments/201871749/2451-TransArmor-CNP-visual-update__2_.png "Logo Title Text 1")


# Using TransArmor Tokens with a Test Account

In the test account environment the TransArmor token is returned as random letters and numbers with the last 4 digits the same as the card number.  In the production environment the token is returned as only random numbers with the last 4 digits the same as the card number. 

# Multi-Pay Tokens

The original concept of the token meant that the merchant could not use this random number to perform a subsequent financial transaction, because it is not a valid PAN. However, a multi-pay token adds the ability to perform an authorized financial transaction under strict control measures within the merchant environment. The merchant submits a token that it already has on file for a specific consumer/card to First Data who accesses the vault to retrieve the PAN and complete the transaction. By using this type of token in the payment authorization process, the merchant reduces the risk of having the real PAN stolen as it is being collected from the consumer or stored by the merchant.

Multi-pay tokens are especially valuable in eCommerce and other CNP environments that tend to store payment card information in a virtual wallet or on their website for repeat customers. The multi-pay token allows a merchant to tokenize the payment card information, associate that token with the consumer profile stored on the merchant side, and then use the token with the processor gateway that holds the token vault in order to run subsequent transactions. This is done without the need to prompt the customer for his card account number again, and without having to store the actual card number.
