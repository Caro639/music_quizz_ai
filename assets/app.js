import { registerReactControllerComponents } from "@symfony/ux-react/loader";
import { startStimulusApp } from "@symfony/stimulus-bundle/loader";
import ReactController from "@symfony/ux-react/render_controller";
/*
 * Welcome to your app's main JavaScript file!
 *
 * This file will be included onto the page via the importmap() Twig function,
 * which should already be in your base.html.twig.
 */
import "./styles/app.css";

const app = startStimulusApp();
// L'identifiant Stimulus est dérivé de '@symfony/ux-react/react' -> 'symfony--ux-react--react'
app.register("symfony--ux-react--react", ReactController);

// Construit le dictionnaire { NomComposant: Component } attendu par registerReactControllerComponents
const context = require.context("./react/controllers", true, /\.(j|t)sx?$/);
const components = {};
context.keys().forEach((key) => {
  // "./QuizzLobby.jsx" -> "QuizzLobby"
  const name = key.replace(/^\.\//, "").replace(/\.(jsx?|tsx?)$/, "");
  components[name] = context(key).default;
});

registerReactControllerComponents(components);
