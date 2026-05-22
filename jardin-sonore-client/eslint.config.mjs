import nextVitals from "eslint-config-next/core-web-vitals";
import nextTypescript from "eslint-config-next/typescript";

const eslintConfig = [
  ...nextVitals,
  ...nextTypescript,
  {
    ignores: [".next/**", ".next-*/**", "node_modules/**", "out/**", "dist/**", "build/**", "next-env.d.ts"],
  },
];

export default eslintConfig;
