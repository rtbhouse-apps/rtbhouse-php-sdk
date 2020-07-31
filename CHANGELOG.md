# v4.0.0
This version adapts to latest api v4 changes.

`getRtbCreatives` now provides faster results with different output:
Refer to `https://panel.rtbhouse.com/api/docs` - `GET /advertisers/{hash}/rtb-creatives` for details

# v3.0.0
This version adapts to latest api v3. changes.

Multiple stats loading functions: `getRtbCreativeCountryStats`, `getRtbCountryStats`, `getRtbDeviceStats`, `getRtbCreativeStats`, `getRtbCategoryStats`, `getCampaignStatsTotal`, `getRtbCampaignStats` are now replaced with single `getRtbStats` method, see below.
- `campaign` in `groupBy` is renamed to `subcampaign`.
- `categoryId` grouping is renamed to `category`. In output `categoryId` is removed, `category` now contains category identifier (previously name) and new field `categoryName` is added.
- `creativeId` grouping is renamed to `creative`. In output `hash` is renamed to `creative`. All creative details are prefixed with `creative` (`creativeName`, `creativeWidth`, `creativeCreatedAt`).
- `conversionsRate` in output is renamed to `cr`.
- Indeterminate values (ex. ctr when there are no imps and clicks) are now `null`, previously `0`.

For example:
- `getRtbCampaignStats` equals to `getRtbStats`, with default `groupBy` set to `['day']`.
- `getCampaignStatsTotal` equals to `getRtbStats`, with default `groupBy` set to `['day']` and `includeDpa` set to `true`.
- `getRtbCategoryStats` equals to `getRtbStats` with `groupBy` set to `['category']`.
- `getRtbCreativeStats` equals to `getRtbStats` with `groupBy` set to `['creative']`.
- `getRtbDeviceStats` equals to `getRtbStats` with `groupBy` set to `['deviceType']`.
- `getRtbCountryStats` equals to `getRtbStats` with `groupBy` set to `['country']`.
- `getRtbCreativeCountryStats` equals to `getRtbStats` with `groupBy` set to `['creative', 'country']`.
