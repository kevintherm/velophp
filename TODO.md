## TODO

- fix: Support strongly typed argument for actions e.g UpdateCOllectionAction fields is accepting array instead of Field vo
- fix: PATCH supposed to modify only the field sent in the payload, while PUT is supposed to replace the entire resource. 
- fix: Sending protected fields even with the same value triggers 422 status e.g Email -> Email change flow
- feat: Provide handler for common app exception e.g InvalidRuleExpressionException -> 422 or 400

- Battle Testing

## Roadmap

- Implement explicit database check for sensitive operations (bypassing the token cache)
- Add support for :changed, :isset, :length for api rules
- Add expand option to realtime
- Add support for indexing json and file field
- Add oauth link setting for auth collection (per-user)
- Add octane support?
- Full db backup?
- Schema SDK sync (Typed)?
