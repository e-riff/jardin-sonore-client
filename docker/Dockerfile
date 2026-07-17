FROM node:24-alpine

WORKDIR /app
ENV NEXT_TELEMETRY_DISABLED=1

RUN apk add --no-cache su-exec

COPY jardin-sonore-client/package*.json ./

RUN npm install

COPY jardin-sonore-client .
COPY docker/client/entrypoint.sh /usr/local/bin/jardin-sonore-client-entrypoint

RUN chmod +x /usr/local/bin/jardin-sonore-client-entrypoint

EXPOSE 3000

ENTRYPOINT ["/usr/local/bin/jardin-sonore-client-entrypoint"]
CMD ["npm", "run", "dev", "--", "--hostname", "0.0.0.0"]
