FROM node:22-alpine AS base
WORKDIR /usr/src/app
COPY package*.json ./

FROM base AS development
RUN npm install
COPY . .
CMD ["npm", "run", "dev"]

FROM base AS production
ENV NODE_ENV=production
RUN npm ci --omit=dev
COPY --chown=node:node . .
USER node
EXPOSE 8080
CMD ["node", "server.js"]
