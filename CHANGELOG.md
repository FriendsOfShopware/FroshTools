# 3.17.2

**Fixed**
- Granted the Shopmon integration role `message_queue_stats:read`, allowing Shopmon to retrieve queue statistics instead of receiving a missing-privilege error. Existing integrations receive the permission when the role is refreshed on plugin update. [#508](https://github.com/FriendsOfShopware/FroshTools/pull/508)
- Removed unwanted whitespace around recommendation code displayed in the Administration. [#507](https://github.com/FriendsOfShopware/FroshTools/pull/507)

