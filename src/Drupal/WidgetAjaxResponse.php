<?php

declare(strict_types=1);

namespace SchemaForms\Drupal;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\AnnounceCommand;
use Drupal\Core\Ajax\FocusFirstCommand;
use Drupal\Core\Ajax\InsertCommand;
use Drupal\Core\Ajax\PrependCommand;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Builds the AJAX responses of the multivalue widget buttons.
 *
 * Besides replacing the widget, each response announces the change to
 * screen readers and moves focus, which a removal would otherwise drop.
 *
 * @internal
 */
final class WidgetAjaxResponse {

  /**
   * Builds the response to an appended item.
   *
   * @param array<int|string, mixed> $widget
   *   The rebuilt widget.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The response, which focuses the new item's first control.
   */
  public static function appended(array $widget): AjaxResponse {
    $response = self::replace($widget);
    $delta = $widget[RenderKeys::MAX_DELTA] ?? NULL;
    $item = is_int($delta) ? MultivalueWidget::item($widget, $delta) : NULL;
    if (!is_int($delta) || $item === NULL) {
      return $response;
    }
    $response->addCommand(new AnnounceCommand((string) new TranslatableMarkup('Item added: @item.', [
      '@item' => Labels::placeholder($item['#title'] ?? ''),
    ])));
    return self::focus($response, self::itemSelector($widget, $delta) ?? self::wrapperSelector($widget));
  }

  /**
   * Builds the response to a removed item.
   *
   * @param array<int|string, mixed> $widget
   *   The rebuilt widget, which no longer holds the item.
   * @param int $removed
   *   The delta of the removed item.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The response. It focuses the item that took the removed one's place,
   *   or the Append button when the last item went, or the widget.
   */
  public static function removed(array $widget, int $removed): AjaxResponse {
    $response = self::replace($widget);
    $deltas = self::itemDeltas($widget);
    $before = array_filter($deltas, static fn (int $delta): bool => $delta < $removed);
    $after = array_values(array_filter($deltas, static fn (int $delta): bool => $delta > $removed));
    $item = Labels::itemTitle($widget['#title'] ?? '', count($before) + 1);
    $response->addCommand(new AnnounceCommand((string) new TranslatableMarkup('Item removed: @item.', ['@item' => $item])));
    $next = $after === [] ? NULL : self::itemSelector($widget, $after[0]);
    return self::focus($response, $next ?? self::appendSelector($widget) ?? self::wrapperSelector($widget));
  }

  /**
   * Starts a response that replaces the widget and shows the messages.
   *
   * The messages go into the rebuilt wrapper: the rebuild gives the widget a
   * new wrapper ID, so the clicked button's wrapper is gone after the insert.
   *
   * @param array<int|string, mixed> $widget
   *   The rebuilt widget.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The response. Without a rebuilt wrapper, the messages target the
   *   clicked button's wrapper, as Drupal's own response does.
   */
  private static function replace(array $widget): AjaxResponse {
    $response = new AjaxResponse();
    $response->addCommand(new InsertCommand(NULL, $widget));
    $response->addCommand(new PrependCommand(self::wrapperSelector($widget), ['#type' => 'status_messages']));
    return $response;
  }

  /**
   * Adds the command that moves focus, when there is a target.
   *
   * @param \Drupal\Core\Ajax\AjaxResponse $response
   *   The response.
   * @param string|null $selector
   *   The selector of the element to focus, or NULL for none.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The response.
   */
  private static function focus(AjaxResponse $response, ?string $selector): AjaxResponse {
    if ($selector === NULL) {
      return $response;
    }
    $response->addCommand(new FocusFirstCommand($selector));
    return $response;
  }

  /**
   * Lists the deltas of the widget's items.
   *
   * @param array<int|string, mixed> $widget
   *   The widget.
   *
   * @return list<int>
   *   The deltas, in form order.
   */
  private static function itemDeltas(array $widget): array {
    $keys = array_filter(RenderChildren::keys($widget), 'is_int');
    return array_values(array_filter($keys, static fn (int $delta): bool => MultivalueWidget::item($widget, $delta) !== NULL));
  }

  /**
   * Gives the selector of an item container.
   *
   * @param array<int|string, mixed> $widget
   *   The widget.
   * @param int $delta
   *   The item delta.
   *
   * @return string|null
   *   The selector, or NULL when the item has no wrapper ID.
   */
  private static function itemSelector(array $widget, int $delta): ?string {
    $container = is_array($widget[$delta] ?? NULL) ? $widget[$delta] : [];
    $id = $container['#wrapper_id'] ?? NULL;
    return is_string($id) ? '#' . $id : NULL;
  }

  /**
   * Gives the selector of the Append button, when the user can reach it.
   *
   * @param array<int|string, mixed> $widget
   *   The widget.
   *
   * @return string|null
   *   The selector, or NULL when the button is hidden or has no ID yet.
   */
  private static function appendSelector(array $widget): ?string {
    $button = is_array($widget[RenderKeys::APPEND_BUTTON] ?? NULL) ? $widget[RenderKeys::APPEND_BUTTON] : [];
    $id = $button['#id'] ?? NULL;
    return ($button['#access'] ?? TRUE) !== FALSE && is_string($id) ? '#' . $id : NULL;
  }

  /**
   * Gives the selector of the widget wrapper.
   *
   * @param array<int|string, mixed> $widget
   *   The widget.
   *
   * @return string|null
   *   The selector, or NULL when the element is not a widget. A bare "#"
   *   would make jQuery throw in the browser.
   */
  private static function wrapperSelector(array $widget): ?string {
    $button = is_array($widget[RenderKeys::APPEND_BUTTON] ?? NULL) ? $widget[RenderKeys::APPEND_BUTTON] : [];
    $ajax = is_array($button['#ajax'] ?? NULL) ? $button['#ajax'] : [];
    $wrapper = $ajax['wrapper'] ?? NULL;
    return is_string($wrapper) && $wrapper !== '' ? '#' . $wrapper : NULL;
  }

}
